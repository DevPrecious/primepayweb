<?php

namespace App\Filament\User\Pages;

use App\Models\RecentActivity;
use App\Models\Transaction;
use App\Models\Wallet;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class FirstServer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static string $view = 'filament.user.pages.first-server';

    protected static ?string $navigationGroup = 'Virtual Number';

    protected ?string $heading = 'Purchase Virtual Number';

    public $data = [
        'services' => [],
        'plans' => [],
    ];

    public function mount()
    {
        $this->form->fill();

        try {
            $response = Http::timeout(10)->get("https://www.tellabot.com/sims/api_command.php?", [
                'cmd' => 'list_services',
                'user' => env('TELLABOT_USERNAME'),
                'api_key' => env('TELLABOT_API_KEY')
            ]);

            // dd($response->json());

            $responseData = $response->json();

            if ($responseData['status'] === 'ok') {
                $this->data['services'] = $responseData['message'];
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema(
                [
                    Select::make('service')
                        ->label('Select Service')
                        ->options(function () {
                            $options = [];
                            foreach ($this->data['services'] as $service) {
                                $options[$service['name']] = $service['name'];
                            }
                            return $options;
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            if ($state) {
                                $selectedService = collect($this->data['services'])->firstWhere('name', $state);
                                if ($selectedService) {
                                    $this->data['amount'] = $selectedService['price'];
                                }
                            }
                        }),

                    TextInput::make('amount')
                        ->label('Amount')
                        ->required()
                        ->numeric()
                        ->readOnly()
                        ->default(fn() => $this->data['amount'] ?? null)
                        ->placeholder('Service price will appear here')
                ]
            );
    }

    public function getFormActions(): array
    {
        return [
            Action::make()->submit('purchase')->label('Purchase')->icon('heroicon-o-wallet')
        ];
    }

    public function purchase()
    {
        // dd($this->form->getState());
        try {
            $balance = Wallet::where('user_id', Auth::id())->first();
            if ($balance->balance < $this->amount) {
                Notification::make()
                    ->title('Insufficient balance')
                    ->body('You do not have enough balance to make this purchase.')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->send();
                return;
            }
            $response = Http::timeout(10)->get("https://www.tellabot.com/sims/api_command.php?", [
                'cmd' => 'request',
                'user' => env('TELLABOT_USERNAME'),
                'api_key' => env('TELLABOT_API_KEY'),
                'service' => $this->form->getState()['service'],
            ]);

            // dd($response->json());

            $responseData = $response->json();

            if ($responseData['status'] != 'ok') {

                RecentActivity::create([
                    'user_id' => Auth::user()->id,
                    'action' => 'Virtual Number',
                    'status' => 'failed',
                    'message' => "Unable to purchase service.",
                    'type' => "Tellabot",
                    'amount' => $this->form->getState()['amount'],
                ]);

                Notification::make()
                    ->title('Error')
                    ->body('Unable to purchase service.')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->send();
            }

            $message = $responseData['message'][0];

            Transaction::create([
                'user_id' => Auth::user()->id,
                'transaction_id' => $message['id'],
                'type' => "Tellabot",
                'status' => 'success',
                'amount' => $this->form->getState()['amount'],
                'phone_number' => $message['mdn'],
                'reference' => $message['id'],
                'mdn' => $message['mdn'],
                'service' => $this->form->getState()['service'],
            ]);

            RecentActivity::create([
                'type' => "Tellabot",
                'user_id' => Auth::user()->id,
                'action' => "Tellabot",
                'status' => 'success',
                'message' => "Successfully purchased virtual number.",
                'amount' => $this->form->getState()['amount'],
                'phone_number' => $message['mdn'],
                'reference' => $message['id'],
                'mdn' => $message['mdn'],
                'service' => $this->form->getState()['service'],
            ]);

            Notification::make()
                ->title('Success')
                ->body('Successfully purchased virtual number.')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->send();
        }
    }

    public function loadPlans($service)
    {
        $this->data['plans'] = [];
        $this->form->fill($this->data);
    }
}
