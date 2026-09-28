<?php

namespace Tests\Fakes;

use App\Contracts\PaymentProviderContract;

class AlwaysFailsProvider implements PaymentProviderContract
{
    public function charge(string $idempotencyKey, int $amountCents): array
    {
        return ['status' => 'failed'];
    }

    public function checkStatus(string $idempotencyKey): string
    {
        return 'failed';
    }
}
