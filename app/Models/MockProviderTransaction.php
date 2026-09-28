<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MockProviderTransaction extends Model
{
    protected $fillable = ['idempotency_key', 'amount_cents', 'status', 'reference'];
}
