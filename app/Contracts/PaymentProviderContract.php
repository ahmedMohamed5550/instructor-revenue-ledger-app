<?php

namespace App\Contracts;

interface PaymentProviderContract
{
    public function charge(string $idempotencyKey, int $amountCents): array;

    public function checkStatus(string $idempotencyKey): string;
}
