<?php

namespace App\Filament\User\Pages;

use App\Models\RecentActivity;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SecondServer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static string $view = 'filament.user.pages.second-server';

    protected static ?string $navigationGroup = 'Virtual Number';

    protected ?string $heading = 'Purchase Virtual Number';

    public $data = [
        'services' => [],
        'selectedService' => null,
        'service' => null
    ];

    public function mount()
    {
        try {
            $response = Http::timeout(10)->get('https://daisysms.com/stubs/handler_api.php', [
                'api_key' => env('DAISYSMS_API_KEY'),
                'action' => 'getPrices'
            ]);

            // dd($response->json());

            $responseData = $response->json()[187];

            if (!empty($responseData)) {
                $this->data['services'] = $responseData;
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
                    Select::make('selectedService')
                        ->label('Select Service')
                        ->options(function () {
                            $options = [];
                            foreach ($this->data['services'] as $key => $service) {
                                $options[$key] = $service['name'];
                            }
                            return $options;
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            if ($state) {
                                $selectedService = $this->data['services'][$state] ?? null;
                                if ($selectedService) {
                                    $this->data['amount'] = $selectedService['cost'];
                                    $this->data['service'] = $selectedService['name']; // Store the name
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
        try {
            $data = $this->form->getState();
            $selectedService = $this->data['services'][$data['selectedService']] ?? null;

            if (!$selectedService) {
                Notification::make()
                    ->title('Error')
                    ->body('Invalid service selected.')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->send();
                return;
            }

            $response = Http::timeout(10)->get("https://daisysms.com/stubs/handler_api.php", [
                'api_key' => env('DAISYSMS_API_KEY'),
                'action' => 'getNumber',
                'service' => $data['selectedService'],
            ]);

            $responseText = $response->body();

            // Check if response starts with ACCESS_NUMBER
            if (!str_starts_with($responseText, 'ACCESS_NUMBER:')) {
                RecentActivity::create([
                    'user_id' => Auth::user()->id,
                    'action' => 'Virtual Number',
                    'status' => 'failed',
                    'message' => "Unable to purchase service.",
                    'type' => "Daisy",
                    'amount' => $data['amount'],
                ]);

                Notification::make()
                    ->title('Error')
                    ->body('Unable to purchase service.')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->send();

                return;
            }

            // Parse the response - format is ACCESS_NUMBER:id:phone_number
            $parts = explode(':', $responseText);
            $id = $parts[1];
            $phoneNumber = $parts[2];

            Transaction::create([
                'user_id' => Auth::user()->id,
                'transaction_id' => $id,
                'type' => "Daisy",
                'status' => 'success',
                'amount' => $data['amount'],
                'phone_number' => $phoneNumber,
                'reference' => $id,
                'mdn' => $phoneNumber,
                'service' => $selectedService['name'],
            ]);

            RecentActivity::create([
                'type' => "Daisy",
                'user_id' => Auth::user()->id,
                'status' => 'success',
                'message' => "Successfully purchased virtual number.",
                'amount' => $data['amount'],
                'phone_number' => $phoneNumber,
                'reference' => $id,
                'mdn' => $phoneNumber,
                'service' => $selectedService['name'],
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
