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
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class Airtime extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static string $view = 'filament.user.pages.airtime';
    protected static ?string $navigationGroup = 'Bills';
    protected static ?string $navigationLabel = 'Purchase Airtime';
    protected ?string $heading = 'Purchase Airtime';
    protected ?string $breadcrumb = 'Purchase Airtime';

    public $selectedNetwork;
    public $phoneNumber;
    public $amount;


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Purchase Data')
                    ->schema([
                        Select::make('selectedNetwork')
                            ->label('Select Network')
                            ->options([
                                '1' => 'MTN',
                                '2' => 'GLO',
                                '4' => 'AIRTEL',
                                '3' => '9MOBILE',
                            ])
                            ->required(),

                        TextInput::make('phoneNumber')
                            ->label('Enter Phone Number')
                            ->required()
                            ->numeric()
                            ->length(11),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->minValue(100)
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

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . env('DATA_STORE_TOKEN'),
                'Content-Type' => 'application/json',
            ])->post('https://datastation.com.ng/api/topup/', [
                'network' => $data['selectedNetwork'],
                'amount' => $data['amount'],
                'mobile_number' => $data['phoneNumber'],
                'Ported_number' => true,
                'airtime_type' => 'VTU'
            ]);

            // dd($response->json());

            $selectedNet = $data['selectedNetwork'];
            if ($selectedNet == 1) {
                $selectedNet = 'MTN';
            } else if ($selectedNet == 2) {
                $selectedNet = 'GLO';
            } else if ($selectedNet == 3) {
                $selectedNet = '9MOBILE';
            } else if ($selectedNet == 4) {
                $selectedNet = 'AIRTEL';
            }


            if (!$response->successful()) {
                RecentActivity::create([
                    'user_id' => $this->user()->id,
                    'type' => 'airtime',
                    'reference' => null,
                    'amount' => $data['amount'],
                    'status' => 'failed',
                    'message' => 'Unable to process airtime purchase. Please try again.',
                    'network' => $data['selectedNetwork'],
                    'phone_number' => $data['phoneNumber'],
                    'transaction_id' => null
                ]);
                Notification::make()
                    ->title('Error')
                    ->body('Unable to process airtime purchase. Please try again.')
                    ->danger()
                    ->send();
                return;
            }

            $responseData = $response->json();

            if ($responseData['Status'] !== 'successful') {
                RecentActivity::create([
                    'user_id' => Auth::id(),
                    'type' => 'airtime',
                    'reference' => null,
                    'amount' => $data['amount'],
                    'status' => 'failed',
                    'message' => "Error processing airtime purchase. Please try again.",
                    'network' => $selectedNet,
                    'phone_number' => $data['phoneNumber'],
                    'transaction_id' => null
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
                'type' => 'airtime',
                'reference' => $responseData['ident'],
                'amount' => $data['amount'],
                'status' => 'success',
                'message' => 'Airtime purchase successful!',
                'network' => $selectedNet,
                'phone_number' => $data['phoneNumber'],
                'transaction_id' => $responseData['id']
            ]);

            Transaction::create([
                'user_id' => Auth::id(),
                'type' => 'airtime',
                'reference' => $responseData['ident'],
                'amount' => $data['amount'],
                'status' => 'success',
                'message' => 'Airtime purchase successful!',
                'network' => $selectedNet,
                'phone_number' => $data['phoneNumber'],
                'transaction_id' => $responseData['id']
            ]);

            Notification::make()
                ->title('Success')
                ->body('Airtime purchase successful!')
                ->success()
                ->send();
        } catch (\Exception $e) {

            RecentActivity::create([
                'user_id' => Auth::id(),
                'type' => 'airtime',
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
