<?php

namespace App\Filament\User\Pages;

use App\Models\RecentActivity;
use App\Models\Transaction;
use App\Models\Wallet;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class Data extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static string $view = 'filament.user.pages.data';
    protected static ?string $navigationGroup = 'Bills';
    protected static ?string $navigationLabel = 'Purchase Data';
    protected ?string $heading = 'Purchase Data';
    protected ?string $breadcrumb = 'Purchase Data';

    public $data = [
        'selectedNetwork' => '',
        'selectedPlan' => '',
        'phoneNumber' => '',
        'amount' => '',
        'planPrices' => [],
    ];

    public $loadingPlans = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function updatedSelectedNetwork($value)
    {
        $this->data['selectedPlan'] = '';
    }

    private function fetchUserData()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . env('DATA_STORE_TOKEN'),
            'Content-Type' => 'application/json',
        ])->get('https://datastation.com.ng/api/user/');

        if ($response->successful()) {
            $userData = $response->json();
            $this->data['availablePlans'] = $userData['Dataplans'] ?? [];
        }
    }

    public function getNetworkPlansProperty()
    {
        if (!$this->data['selectedNetwork']) {
            return [];
        }

        $this->loadingPlans = true;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . env('DATA_STORE_TOKEN'),
                'Content-Type' => 'application/json',
            ])->get('https://datastation.com.ng/api/user/');

            if (!$response->successful()) {
                $this->loadingPlans = false;
                return [];
            }

            $data = $response->json();

            // dd($data['Dataplans']['MTN_PLAN']['ALL']);

            $planKey = match ($this->data['selectedNetwork']) {
                'MTN_PLAN' => 'MTN_PLAN',
                'GLO_PLAN' => 'GLO_PLAN',
                'AIRTEL_PLAN' => 'AIRTEL_PLAN',
                '9MOBILE_PLAN' => '9MOBILE_PLAN',
                default => null
            };

            // dd($planKey);

            if (!$planKey || !isset($data['Dataplans'][$planKey])) {
                $this->loadingPlans = false;
                return [];
            }

            $plans = $data['Dataplans'][$planKey]['ALL'];

            $formattedPlans = [];
            $this->data['planPrices'] = [];

            foreach ($plans as $plan) {
                $formattedPlans[$plan['dataplan_id']] = "{$plan['plan']} - {$plan['month_validate']} - ₦{$plan['plan_amount']}";
                $this->data['planPrices'][$plan['dataplan_id']] = $plan['plan_amount'];
            }

            $this->loadingPlans = false;
            return $formattedPlans;
        } catch (\Exception $e) {
            $this->loadingPlans = false;
            return [];
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Purchase Data')
                    ->schema([
                        Select::make('selectedNetwork')
                            ->label('Select Network')
                            ->options([
                                'MTN_PLAN' => 'MTN',
                                'GLO_PLAN' => 'GLO',
                                'AIRTEL_PLAN' => 'AIRTEL',
                                '9MOBILE_PLAN' => '9MOBILE',
                            ])
                            ->required()
                            ->live(),

                        Select::make('selectedPlan')
                            ->label('Select Data Plan')
                            ->options(fn() => $this->getNetworkPlansProperty())
                            ->visible(fn() => filled($this->data['selectedNetwork']))
                            ->disabled(fn() => $this->loadingPlans)
                            ->helperText(fn() => $this->loadingPlans ? 'Loading plans...' : '')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state && isset($this->data['planPrices'][$state])) {
                                    $set('amount', $this->data['planPrices'][$state]);
                                } else {
                                    $set('amount', null);
                                }
                            }),

                        TextInput::make('phoneNumber')
                            ->label('Enter Phone Number')
                            ->required()
                            ->numeric()
                            ->minLength(11)
                            ->maxLength(11)
                            ->placeholder('e.g., 08012345678')
                            ->helperText('Enter a valid Nigerian phone number')
                            ->regex('/^0[789][01]\d{8}$/')
                            ->validationMessages([
                                'regex' => 'Please enter a valid Nigerian phone number starting with 070, 080, 081, 090, or 091',
                                'min_length' => 'Phone number must be 11 digits',
                                'max_length' => 'Phone number must be 11 digits',
                                'numeric' => 'Phone number must contain only numbers'
                            ]),
                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->readOnly()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->data['amount'] = $state;
                            })
                            ->default(function (Get $get) {
                                $selectedPlan = $get('selectedPlan');
                                if (!$selectedPlan) return null;

                                return $this->data['planPrices'][$selectedPlan] ?? null;
                            })
                    ])
            ]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make()->submit('purchase')->label('Purchase')->icon('heroicon-o-banknotes')
        ];
    }

    public function purchase()
    {
        $data = $this->form->getState();
        // dd($data);

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
            $selectedNet =  $data['selectedNetwork'];

            // dd($selectedNet);

            if ($selectedNet == "MTN_PLAN")
                $selectedNet = 1;
            if ($selectedNet == "GLO_PLAN")
                $selectedNet = 2;
            if ($selectedNet == "AIRTEL_PLAN")
                $selectedNet = 4;
            if ($selectedNet == "9MOBILE_PLAN")
                $selectedNet = 3;

            // dd($selectedNet);

            $selectedNetName = $selectedNet;

            if ($selectedNetName == 1)
                $selectedNetName = "MTN";
            if ($selectedNetName == 2)
                $selectedNetName = "GLO";
            if ($selectedNetName == 3)
                $selectedNetName = "9MOBILE";
            if ($selectedNetName == 4)
                $selectedNetName = "AIRTEL";

            $response = Http::withHeaders([
                'Authorization' => 'Token ' . env('DATA_STORE_TOKEN'),
                'Content-Type' => 'application/json',
            ])->post('https://datastation.com.ng/api/data/', [
                'network' => $selectedNet,
                'mobile_number' => $data['phoneNumber'],
                'plan' => $data['selectedPlan'],
                'Ported_number' => true
            ]);

            // dd($response->json());

            if (!$response->successful()) {
                RecentActivity::create([
                    'user_id' => Auth::id(),
                    'type' => 'data',
                    'reference' => null,
                    'amount' => $data['amount'],
                    'status' => 'failed',
                    'message' => $response->json('error')[0],
                    'network' => $selectedNetName,
                    'phone_number' => $data['phoneNumber'],
                    'transaction_id' => null
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
                    'type' => 'data',
                    'reference' => null,
                    'amount' => $data['amount'],
                    'status' => 'failed',
                    'message' => "Error processing data purchase. Please try again.",
                    'network' => $selectedNetName,
                    'phone_number' => $data['phoneNumber'],
                    'transaction_id' => null
                ]);

                Notification::make()
                    ->title('Error')
                    ->body("Error processing data purchase. Please try again.")
                    ->danger()
                    ->send();
                return;
            }

            RecentActivity::create([
                'user_id' => Auth::id(),
                'type' => 'data',
                'reference' => $responseData['ident'],
                'amount' => $data['amount'],
                'status' => 'success',
                'message' => 'Data purchase successful!',
                'network' => $selectedNetName,
                'phone_number' => $data['phoneNumber'],
                'transaction_id' => $responseData['id']
            ]);

            Transaction::create([
                'user_id' => Auth::id(),
                'type' => 'data',
                'reference' => $responseData['ident'],
                'amount' => $data['amount'],
                'status' => 'success',
                'message' => 'Data purchase successful!',
                'network' => $selectedNetName,
                'phone_number' => $data['phoneNumber'],
                'transaction_id' => $responseData['id']
            ]);

            Notification::make()
                ->title('Success')
                ->body('Data purchase successful!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Transaction::create([
                'user_id' => Auth::id(),
                'type' => 'data',
                'reference' => null,
                'amount' => $data['amount'],
                'status' => 'failed',
                'message' => $e->getMessage(),
                'network' => $selectedNet,
                'phone_number' => $data['phoneNumber'],
                'transaction_id' => null
            ]);

            RecentActivity::create([
                'user_id' => Auth::id(),
                'type' => 'data',
                'reference' => null,
                'amount' => $data['amount'],
                'status' => 'failed',
                'message' => $e->getMessage(),
                'network' => $selectedNet,
                'phone_number' => $data['phoneNumber'],
                'transaction_id' => null
            ]);

            Notification::make()
                ->title('Error')
                ->body('An error occurred while processing your request.')
                ->danger()
                ->send();
        }
    }
}
