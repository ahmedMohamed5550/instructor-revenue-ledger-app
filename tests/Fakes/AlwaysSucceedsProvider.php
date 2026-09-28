<?php

namespace Tests\Fakes;

use App\Contracts\PaymentProviderContract;

class AlwaysSucceedsProvider implements PaymentProviderContract
{
    public array $charges = [];

    public function charge(string $idempotencyKey, int $amountCents): array
    {
        $this->charges[] = $idempotencyKey;

        return ['status' => 'succeeded', 'reference' => 'ref-' . $idempotencyKey];
    }

    public function checkStatus(string $idempotencyKey): string
    {
        return 'succeeded';
    }
}
