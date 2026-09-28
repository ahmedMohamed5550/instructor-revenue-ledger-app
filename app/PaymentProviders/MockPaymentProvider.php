<?php

namespace App\PaymentProviders;

use App\Contracts\PaymentProviderContract;
use App\Exceptions\PaymentProviderTimeoutException;
use App\Models\MockProviderTransaction;
use Illuminate\Support\Str;

/**
 * Simulates a realistic unreliable payment provider:
 *  - 70% of calls: succeeds and tells us immediately.
 *  - 15% of calls: succeeds on the provider's side, but the response never
 *    reaches us (we get a timeout/exception and are left unsure).
 *  - 15% of calls: fails outright and tells us immediately.
 *
 * Crucially, the "truth" is written to mock_provider_transactions BEFORE we
 * decide what to tell the caller - exactly like a real provider that has
 * already moved money before its HTTP response is generated. checkStatus()
 * reads that same source of truth later, independent of the original call.
 */
class MockPaymentProvider implements PaymentProviderContract
{
    public function charge(string $idempotencyKey, int $amountCents): array
    {
        $existing = MockProviderTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $this->toResult($existing);
        }

        $roll = random_int(1, 100);
        $trueOutcome = $roll <= 85 ? 'succeeded' : 'failed';

        $transaction = MockProviderTransaction::create([
            'idempotency_key' => $idempotencyKey,
            'amount_cents' => $amountCents,
            'status' => $trueOutcome,
            'reference' => $trueOutcome === 'succeeded' ? (string) Str::uuid() : null,
        ]);

        if ($roll > 70 && $roll <= 85) {
            throw new PaymentProviderTimeoutException(
                "Provider call for {$idempotencyKey} timed out; outcome unknown to caller."
            );
        }

        return $this->toResult($transaction);
    }

    public function checkStatus(string $idempotencyKey): string
    {
        $transaction = MockProviderTransaction::where('idempotency_key', $idempotencyKey)->first();

        return $transaction?->status ?? 'unknown';
    }

    private function toResult(MockProviderTransaction $transaction): array
    {
        return [
            'status' => $transaction->status,
            'reference' => $transaction->reference,
        ];
    }
}
