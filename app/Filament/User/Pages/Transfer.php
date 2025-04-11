<?php

namespace App\Filament\User\Pages;

use App\Models\RecentActivity;
use App\Models\Transaction;
use App\Models\Wallet;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Transfer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string $view = 'filament.user.pages.transfer';

    public $data = [
        'banks' => []
    ];

    public $accountName;
    public $accountNumber;
    public $bank;
    public $amount;
    public $recipientCode;

    public function mount()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type' => 'application/json',
        ])->get("https://api.paystack.co/bank?currency=NGN");

        $responseData = $response->json();

        if ($responseData['status'] && isset($responseData['data'])) {
            $this->data['banks'] = collect($responseData['data'])
                ->pluck('name', 'code')
                ->toArray();
        }
    }

    public function getAccountDetails()
    {

        if (!$this->accountNumber || !$this->bank) {
            return;
        }
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type' => 'application/json',
        ])->get("https://api.paystack.co/bank/resolve?account_number={$this->accountNumber}&bank_code={$this->bank}");

        $responseData = $response->json();

        if ($responseData['status'] && isset($responseData['data'])) {
            $this->accountName = $responseData['data']['account_name'];
        }
    }


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Transfer to Bank')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->minValue(100),

                        TextInput::make('accountNumber')
                            ->label('Enter Account Number')
                            ->required()
                            ->numeric(),

                        Select::make('bank')
                            ->label('Select Bank')
                            ->searchable()
                            ->options($this->data['banks'])
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->getAccountDetails())
                            ->required(),

                        TextInput::make('accountName')
                            ->label('Account Name')
                            ->readOnly()
                            ->required()
                            ->length(11),
                    ])
            ]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make()->submit('transfer')->label('Transfer')->icon('heroicon-o-paper-airplane')
        ];
    }

    public function createTransferRecipient()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type' => 'application/json',
        ])->post("https://api.paystack.co/transferrecipient", [
            'name' => $this->accountName,
            'type' => 'nuban',
            'bank_code' => $this->bank,
            'account_number' => $this->accountNumber,
            'currency' => 'NGN',
        ]);

        $responseData = $response->json();

        if ($responseData['status'] && isset($responseData['data'])) {
            // return $responseData['data']['recipient_code'];
            $this->recipientCode = $responseData['data']['recipient_code'];
        }

        return null;
    }

    public function createTransfer()
    {
        // dd($this->data['banks'][$this->bank],);
        $ref = 'PrimePay_' . time() . '_' . Str::random(16);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type' => 'application/json',
        ])->post("https://api.paystack.co/transfer", [
            'source' => 'balance',
            'reference' => $ref,
            'recipient' => $this->recipientCode,
            'amount' => $this->amount * 100,
        ]);

        $responseData = $response->json();

        // dd($responseData);

        Log::info('Transfer Request', [
            'response' => $responseData
        ]);

        if ($responseData['status'] && $responseData['message'] == 'Transfer has been queued') {

            Notification::make()
                ->title('Transfer queued')
                ->body('Transfer is being processed')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->send();

            RecentActivity::create([
                'user_id' => Auth::id(),
                'type' => 'transfer',
                'reference' => $ref,
                'amount' => $this->amount,
                'status' => 'pending',
                'message' => 'Transfer queued'
            ]);

            Transaction::create([
                'user_id' => Auth::id(),
                'type' => 'transfer',
                'reference' => $ref,
                'amount' => $this->amount,
                'status' => 'success',
                'account_number' => $this->accountNumber,
                'account_name' => $this->accountName,
                'account_bank'  => $this->data['banks'][$this->bank],
            ]);
            //clear the form
            $this->reset(['accountName', 'accountNumber', 'bank', 'amount', 'recipientCode']);
        } else {
            Notification::make()
                ->title('Failed to create transfer')
                ->body('Failed to create transfer')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->send();
            RecentActivity::create([
                'user_id' => Auth::id(),
                'type' => 'transfer',
                'reference' => $ref,
                'amount' => $this->amount,
                'status' => 'failed',
                'message' => 'Failed to create transfer'
            ]);
        }

        return null;
    }

    public function transfer()
    {
        try {
            // get user balance
            $balance = Wallet::where('user_id', Auth::id())->first();
            if ($balance->balance < $this->amount) {
                Notification::make()
                    ->title('Insufficient balance')
                    ->body('You do not have enough balance to make this transfer')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->send();
                return;
            }
            // First create transfer recipient
            $this->createTransferRecipient();

            if (!$this->recipientCode) {
                Notification::make()
                    ->title('Failed')
                    ->body('Could not create transfer recipient')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->send();
                return;
            }

            // Then create the transfer
            $this->createTransfer();

            // Log the successful completion
            Log::info('Transfer process completed', [
                'user_id' => Auth::id(),
                'amount' => $this->amount,
                'recipient_code' => $this->recipientCode,
                'account_number' => $this->accountNumber,
                'bank_code' => $this->bank,
                'account_name' => $this->accountName
            ]);
        } catch (\Exception $e) {
            Log::error('Transfer process failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'amount' => $this->amount,
                'account_number' => $this->accountNumber,
                'bank_code' => $this->bank
            ]);

            Notification::make()
                ->title('Error')
                ->body('An error occurred during transfer')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->send();
        }
    }
}
