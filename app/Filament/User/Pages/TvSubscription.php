<?php

namespace App\Filament\User\Pages;

use App\Models\RecentActivity;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TvSubscription extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-tv';
    protected static string $view = 'filament.user.pages.tv-subscription';
    protected static ?string $navigationGroup = 'Bills';
    protected static ?string $navigationLabel = 'Purchase TV Subscription';
    protected ?string $heading = 'Purchase TV Subscription';
    protected ?string $breadcrumb = 'Purchase TV Subscription';

    public $data = [
        'smartCardNumber' => '',
        'cableName' => '',
        'customerName' => '',
        'plans' => [],
        'selectedPlan' => '',
        'loadingPlans' => false,
        'amount' => '',
    ];

    public function mount(): void
    {
        $this->form->fill();
    }

    private function loadPlans($cableName)
    {
        $this->data['loadingPlans'] = true;
        $this->form->fill($this->data);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . env('DATA_STORE_TOKEN'),
                'Content-Type' => 'application/json',
            ])->get('https://datastation.com.ng/api/user/');

            if ($response->successful()) {
                $data = $response->json();
                // dd($data);
                $planKey = match ($cableName) {
                    'DSTV' => 'DSTVPLAN',
                    'GOTV' => 'GOTVPLAN',
                    'STARTIME' => 'STARTIMEPLAN',
                    default => ''
                };
                $this->data['plans'] = $data['Cableplan'][$planKey] ?? [];
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Unable to load plans.')
                ->danger()
                ->send();
        }

        $this->data['loadingPlans'] = false;
        $this->form->fill($this->data);
    }

    public function updatedSmartCardNumber($value)
    {
        $this->validateSmartCard($value, $this->data['cableName'] ?? null);
    }

    public function updatedCableName($value)
    {
        if (!empty($this->data['smartCardNumber'])) {
            $this->validateSmartCard($this->data['smartCardNumber'], $value);
        }
    }

    private function validateSmartCard($smartCardNumber, $cableName)
    {
        $this->data['customerName'] = '';
        $this->data['plans'] = [];

        if (empty($smartCardNumber) || empty($cableName)) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . env('DATA_STORE_TOKEN'),
                'Content-Type' => 'application/json',
            ])->get('https://datastation.com.ng/ajax/validate_iuc', [
                'smart_card_number' => $smartCardNumber,
                'cablename' => $cableName,
            ]);

            $data = $response->json();

            if (!$response->successful()) {
                Notification::make()
                    ->title('Validation Error')
                    ->body('Unable to validate smart card number. Please try again.')
                    ->danger()
                    ->send();
                return;
            }

            if ($data['invalid'] ?? true) {
                Notification::make()
                    ->title('Invalid Smart Card')
                    ->body('The smart card number is invalid.')
                    ->warning()
                    ->send();
                return;
            }

            $this->data['customerName'] = $data['name'] ?? '';

            if ($this->data['customerName']) {
                $this->loadPlans($cableName);
                $this->form->fill($this->data);
                Notification::make()
                    ->title('Success')
                    ->body('Smart card validated successfully.')
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('An error occurred while validating the smart card.')
                ->danger()
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Purchase Data')
                    ->schema([
                        Select::make('cableName')
                            ->label('Select Provider')
                            ->options([
                                'DSTV' => 'DSTV',
                                'GOTV' => 'GOTV',
                                'STARTIME' => 'STARTIME',
                            ])
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if (!empty($this->data['smartCardNumber'])) {
                                    $this->validateSmartCard($this->data['smartCardNumber'], $state);
                                }
                            })
                            ->required(),

                        TextInput::make('smartCardNumber')
                            ->label('Enter Smart Card Number')
                            ->live()
                            ->lazy()
                            ->maxLength(20)
                            ->numeric()
                            ->afterStateUpdated(function ($state) {
                                if ($state) {
                                    $this->validateSmartCard($state, $this->data['cableName'] ?? null);
                                }
                            })
                            ->required(),

                        TextInput::make('customerName')
                            ->label('Customer Name')
                            ->disabled()
                            ->placeholder(fn() => $this->data['customerName'] ?? 'No customer found')
                            ->live(),

                        Select::make('selectedPlan')
                            ->label('Select Plan')
                            ->options(function () {
                                $options = [];
                                foreach ($this->data['plans'] as $plan) {
                                    $options[$plan['cableplan_id']] = "{$plan['package']} - ₦{$plan['plan_amount']}";
                                }
                                return $options;
                            })
                            ->visible(fn() => !empty($this->data['customerName']))
                            ->disabled(fn() => $this->data['loadingPlans'])
                            ->helperText(fn() => $this->data['loadingPlans'] ? 'Loading plans...' : '')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if ($state && !empty($this->data['plans'])) {
                                    $selectedPlan = collect($this->data['plans'])->firstWhere('cableplan_id', $state);
                                    if ($selectedPlan) {
                                        $this->data['amount'] = $selectedPlan['plan_amount'];
                                    }
                                }
                            }),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->readOnly()
                            ->placeholder('Plan amount will appear here')
                            ->live()
                    ])
            ]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make()->submit('purchase')->label('Purchase')->icon('heroicon-o-tv')
        ];
    }

    public function purchase()
    {
        // dd($this->form->getState());
        $data = $this->form->getState();
        $amount = (int) $data['amount'];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . env('DATA_STORE_TOKEN'),
                'Content-Type' => 'application/json',
            ])->post('https://datastation.com.ng/api/cablesub/', [
                'cablename' => $data['cableName'],
                'smart_card_number' => $data['smartCardNumber'],
                'cableplan' => $data['selectedPlan'],
            ]);

            // dd($response->json());

            if (!$response->successful()) {
                RecentActivity::create([
                    'user_id' => Auth::id(),
                    'type' => 'tv',
                    'reference' => null,
                    'amount' => $amount,
                    'status' => 'failed',
                    'message' => $response->json('error')[0],
                    'network' => null,
                    'phone_number' => null,
                    'transaction_id' => null,
                    'tv_provider' => $data['cableName'],
                    'smart_card_number' => $data['smartCardNumber'],
                    'cableplan' => $data['selectedPlan'],
                ]);

                Notification::make()
                    ->title('Error')
                    ->body($response->json('error')[0])
                    ->danger()
                    ->send();
                return;
            }

            $responseData = $response->json();
            if ($responseData['Status'] !== 'successful') {
                RecentActivity::create([
                    'user_id' => Auth::id(),
                    'type' => 'tv',
                    'reference' => null,
                    'amount' => $data['amount'],
                    'status' => 'failed',
                    'message' => "Error processing airtime purchase. Please try again.",
                    'network' => null,
                    'phone_number' => null,
                    'transaction_id' => null,
                    'tv_provider' => $data['cableName'],
                    'smart_card_number' => $data['smartCardNumber'],
                    'cableplan' => $data['selectedPlan'],
                ]);

                Notification::make()
                    ->title('Error')
                    ->body("Error processing airtime purchase. Please try again.")
                    ->danger()
                    ->send();
                return;
            }
            RecentActivity::create([
                'user_id' => Auth::id(),
                'type' => 'tv',
                'reference' => $responseData['ident'],
                'amount' => $data['amount'],
                'status' => 'success',
                'message' => 'Airtime purchase successful!',
                'network' => null,
                'phone_number' => null,
                'transaction_id' => $responseData['id'],
                'tv_provider' => $data['cableName'],
                'smart_card_number' => $data['smartCardNumber'],
                'cableplan' => $data['selectedPlan'],
            ]);

            Transaction::create([
                'user_id' => Auth::id(),
                'type' => 'tv',
                'reference' => $responseData['ident'],
                'amount' => $data['amount'],
                'status' => 'success',
                'message' => 'Airtime purchase successful!',
                'network' => null,
                'phone_number' => $data['phoneNumber'],
                'transaction_id' => $responseData['id'],
                'tv_provider' => $data['cableName'],
                'smart_card_number' => $data['smartCardNumber'],
                'cableplan' => $data['selectedPlan'],
            ]);

            Notification::make()
                ->title('Success')
                ->body('Airtime purchase successful!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
