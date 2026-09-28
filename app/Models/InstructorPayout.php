<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;

class InstructorPayout extends Model
{
    protected $fillable = [
        'instructor_id', 'period_key', 'amount_cents', 'status',
        'idempotency_key', 'provider_reference', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'status' => PayoutStatus::class,
        ];
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function earnings()
    {
        return $this->hasMany(InstructorEarning::class, 'payout_id');
    }
}
