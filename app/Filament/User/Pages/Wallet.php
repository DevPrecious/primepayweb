<?php

namespace App\Filament\User\Pages;

use App\Models\AccountNumber;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Wallet extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static string $view = 'filament.user.pages.wallet';



    public function createAccount()
    {

        $user = auth()->user();


        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/dedicated_account/assign', [
                'email' => $user->email,
                'first_name' => explode(' ', $user->name)[0],
                'last_name' => count(explode(' ', $user->name)) > 1 ? explode(' ', $user->name)[1] : '',
                'preferred_bank' => 'wema-bank',
                'phone' => $user->phone_number ?? '',
                'country' => 'NG'
            ]);

            // dd($response->json());

            if ($response->successful() && $response->json()['status']) {
                
                // check wallet

                $user = Wallet::where('user_id', auth()->id())->first();

                if(!$user){
                    Wallet::create([
                        'user_id' => auth()->id(),
                        'balance' => 0
                    ]);
                }else{
                    $user->wallet()->update([
                        'balance' => 0
                    ]);
                }
                
                Notification::make()
                    ->title('Success')
                    ->body('Virtual account created successfully.')
                    ->success()
                    ->send();

                return redirect()->to('user/wallet');
            }

            Log::error('Paystack Virtual Account Creation Failed', [
                'user' => $user->id,
                'error' => $response->json()
            ]);

            Notification::make()
                ->title('Error')
                ->body('Unable to create virtual account.')
                ->danger()
                ->send();

            return false;
        } catch (\Exception $e) {
            Log::error('Paystack Virtual Account Creation Exception', [
                'user' => $user->id,
                'error' => $e->getMessage()
            ]);

            Notification::make()
                ->title('Error')
                ->body('Unable to create virtual account.')
                ->danger()
                ->send();

            return false;
        }
    }
}
