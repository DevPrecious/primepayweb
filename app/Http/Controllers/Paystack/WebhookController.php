<?php

namespace App\Http\Controllers\Paystack;

use App\Http\Controllers\Controller;
use App\Models\AccountNumber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{

    public function handleWebhook(Request $request)
    {
        // Log the received webhook
        Log::info('Webhook received', $request->all());

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'dedicatedaccount.assign.success') {
            return $this->handleDedicatedAccountAssignment($data);
        }

        return response()->json(['message' => 'Event not handled'], 200);
    }

    private function handleDedicatedAccountAssignment($data)
    {
        $customerData = $data['customer'];
        $accountData = $data['dedicated_account'];

        $user = User::where('phone_number', $customerData['phone'])->first();

        // Store or update customer details
        if ($user) {
            AccountNumber::create([
                'user_id' => $user->id,
                'account_number' => $accountData['account_number'],
                'account_name' => $accountData['account_name'],
                'bank_name' => $accountData['bank']['name'],
                'bank_code' => $accountData['bank']['id']
            ]);
        }else{
            Log::info('User not found');
        }

        return response()->json(['message' => 'Dedicated account assigned successfully'], 200);
    }
}
