<?php

namespace App\Providers;

use App\Contracts\PaymentProviderContract;
use App\PaymentProviders\MockPaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProviderContract::class, MockPaymentProvider::class);
    }

    public function boot(): void
    {
    }
}
