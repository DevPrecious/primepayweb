<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecentActivity extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'reference',
        'amount',
        'status',
        'message',
        'network',
        'phone_number',
        'transaction_id',
        'tv_provider',
        'smart_card_number',
        'cableplan',
        'mdn',
        'service',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recents()
    {
        return $this->belongsTo(RecentActivity::class);
    }
}
