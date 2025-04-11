<?php

namespace App\Filament\User\Pages;

use App\Models\RecentActivity;
use App\Models\Transaction;
use App\Models\Wallet;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class VirtualNumbers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.user.pages.virtual-numbers';

    protected static ?string $navigationGroup = 'Virtual Number';

    protected ?string $heading = 'My Virtual Number';


    protected function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->where('user_id', auth()->user()->id)
                    ->where(function ($query) {
                        $query->where('type', 'daisy')
                            ->orWhere('type', 'tellabot');
                    })
            )
            ->columns([
                TextColumn::make('mdn')
                    ->label('Virtual Number')
                    ->searchable(),

                TextColumn::make('service')
                    ->label('Platform')
                    ->searchable(),

                BooleanColumn::make('is_used')
                    ->label('Is Used'),

                TextColumn::make('message')
                    ->label('SMS'),
            ])
            ->filters([
                // Add filters here if needed
            ])
            ->actions([
                Action::make('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Transaction $record) {
                        // dd($record->type);
                        if ($record->type == 'daisy') {

                            $response = \Illuminate\Support\Facades\Http::get('https://daisysms.com/stubs/handler_api.php', [
                                'api_key' => env('DAISYSMS_API_KEY'),
                                'action' => 'getStatus',
                                'id' => $record->transaction_id,
                            ]);

                            $body = $response->body();

                            $status = null;
                            $message = null;

                            if (strpos($body, 'STATUS_OK:') !== false) {
                                $status = 'STATUS_OK';
                                $parts = explode(':', $body);
                                $message = end($parts);
                            } elseif (strpos($body, 'NO_ACTIVATION') !== false) {
                                $status = 'NO_ACTIVATION';
                                $message = 'No Activation';
                            } elseif (strpos($body, 'STATUS_WAIT_CODE') !== false) {
                                $status = 'STATUS_WAIT_CODE';
                                $message = 'Waiting for SMS';
                            } elseif (strpos($body, 'STATUS_CANCEL') !== false) {
                                $status = 'STATUS_CANCEL';
                                $message = 'Rental Cancelled';
                            }

                            // dd($message);

                            // Update the transaction with the message
                            Transaction::where('id', $record->id)
                                ->update([
                                    'message' => $message,
                                    'is_used' => $status == 'STATUS_OK' ? 1 : 0,
                                ]);
                            // Log the response
                            Log::info('Daisy SMS Response', [
                                'response' => $body,
                                'status' => $status,
                                'message' => $message,
                            ]);

                            // Provide feedback to the user
                            Notification::make()
                                ->title('Refresh Successful')
                                ->body('Check if the sms has been received')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->send();
                        } else {
                            $response = \Illuminate\Support\Facades\Http::get('https://www.tellabot.com/sims/api_command.php', [
                                'cmd' => 'read_sms',
                                'user' => env('TELLABOT_USERNAME'),
                                'api_key' => env('TELLABOT_API_KEY'),
                                'mdn' => $record->mdn,
                            ]);

                            $body = $response->json();

                            // dd($body);

                            $status = $body['status'] ?? 'error';
                            $message = null;

                            if ($status === 'ok' && !empty($body['message'])) {
                                // Extract the pin from the first message
                                $message = $body['message'][0]['pin'] ?? 'No PIN found';
                            } else {
                                $message = $body['message'] ?? 'No messages';
                            }

                            // Update the transaction with the message
                            Transaction::where('id', $record->id)
                                ->update([
                                    'message' => $message,
                                    'is_used' => $status === 'ok' ? 1 : 0,
                                ]);

                            // Log the response
                            Log::info('Tellabot SMS Response', [
                                'response' => $body,
                                'status' => $status,
                                'message' => $message,
                            ]);

                            // Provide feedback to the user
                            Notification::make()
                                ->title('Refresh Successful')
                                ->body('Check if the SMS has been received')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->send();
                        }
                    }),
                

                Action::make('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (Transaction $record) {
                        // dd($record->type);
                        if ($record->type == 'Daisy') {
                            $response = \Illuminate\Support\Facades\Http::get('https://daisysms.com/stubs/handler_api.php', [
                                'api_key' => env('DAISYSMS_API_KEY'),
                                'action' => 'setStatus',
                                'id' => $record->transaction_id,
                                'status' => 8,
                            ]);

                            // dd($response);

                            $body = $response->body();

                            if (strpos($body, 'ACCESS_CANCEL') !== false) {
                                // Return user balance
                                Wallet::where('user_id', auth()->user()->id)
                                    ->increment('balance', $record->amount);

                                Transaction::where('id', $record->id)->delete();
                                RecentActivity::where('reference', $record->transaction_id)->delete();

                                Notification::make()
                                    ->title('Reject Successful')
                                    ->body('Virtual number rejected successfully.')
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Reject Failed')
                                    ->body('Failed to reject virtual number.')
                                    ->icon('heroicon-o-x-mark')
                                    ->color('danger')
                                    ->send();
                            }

                            // Log the response
                            Log::info('Daisy SMS Response', [
                                'response' => $body,
                            ]);
                        } else {
                            $response = \Illuminate\Support\Facades\Http::get('https://www.tellabot.com/sims/api_command.php', [
                                'cmd' => 'reject',
                                'user' => env('TELLABOT_USERNAME'),
                                'api_key' => env('TELLABOT_API_KEY'),
                                'id' => $record->transaction_id,
                            ]);

                            $body = $response->json();

                            // Log the response
                            Log::info('Tellabot SMS Response', [
                                'response' => $body,
                            ]);

                            if($body['status'] == 'ok') {

                                // return user balance
                                Wallet::where('user_id', auth()->user()->id)
                                    ->increment('balance', $record->amount);

                                Transaction::where('id', $record->id)->delete();
                                RecentActivity::where('reference', $record->transaction_id)->delete();
                                Notification::make()
                                    ->title('Reject Successful')
                                    ->body('Virtual number rejected successfully.')
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Reject Failed')
                                    ->body('Failed to reject virtual number.')
                                    ->icon('heroicon-o-x-mark')
                                    ->color('danger')
                                    ->send();
                            }
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
