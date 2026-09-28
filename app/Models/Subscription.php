<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'student_id', 'plan_id', 'amount_cents', 'starts_at', 'ends_at',
        'status', 'refunded_at', 'refunded_amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'refunded_at' => 'datetime',
            'status' => SubscriptionStatus::class,
        ];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function courseAccess()
    {
        return $this->hasMany(SubscriptionCourseAccess::class);
    }

    public function earnings()
    {
        return $this->hasMany(InstructorEarning::class);
    }
}
