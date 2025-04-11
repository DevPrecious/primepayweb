<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'transaction_id',
        'type',
        'status',
        'amount',
        'phone_number',
        'network',
        'reference',
        'tv_provider',
        'smart_card_number',
        'cableplan',
        'mdn',
        'service',
        'account_number',
        'account_name',
        'account_bank',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
