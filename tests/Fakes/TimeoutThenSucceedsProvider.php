<?php

namespace Tests\Fakes;

use App\Contracts\PaymentProviderContract;
use App\Exceptions\PaymentProviderTimeoutException;

/**
 * Simulates: the charge() call times out (we never learn the outcome), but
 * the provider actually moved the money - which checkStatus() reveals when
 * the reconciliation job asks later.
 */
class TimeoutThenSucceedsProvider implements PaymentProviderContract
{
    public int $chargeCalls = 0;

    public function charge(string $idempotencyKey, int $amountCents): array
    {
        $this->chargeCalls++;
        throw new PaymentProviderTimeoutException('simulated timeout');
    }

    public function checkStatus(string $idempotencyKey): string
    {
        return 'succeeded';
    }
}
