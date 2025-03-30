<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountNumber extends Model
{
    protected $fillable = [
        'account_number',
        'account_name',
        'bank_name',
        'user_id',
        'bank_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
