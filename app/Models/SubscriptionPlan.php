<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'price_cents', 'duration_days'];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
