<?php

namespace App\Models;

use App\Enums\EarningType;
use Illuminate\Database\Eloquent\Model;

class InstructorEarning extends Model
{
    protected $fillable = [
        'subscription_id', 'instructor_id', 'amount_cents', 'type', 'payout_id', 'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'type' => EarningType::class,
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function payout()
    {
        return $this->belongsTo(InstructorPayout::class, 'payout_id');
    }
}
