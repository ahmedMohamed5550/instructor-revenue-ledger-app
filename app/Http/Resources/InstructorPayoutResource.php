<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InstructorPayout */
class InstructorPayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_key' => $this->period_key,
            'amount_cents' => $this->amount_cents,
            'status' => $this->status->value,
            'provider_reference' => $this->provider_reference,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
