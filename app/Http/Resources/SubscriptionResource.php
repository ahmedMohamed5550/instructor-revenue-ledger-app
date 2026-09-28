<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'plan_id' => $this->plan_id,
            'amount_cents' => $this->amount_cents,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'refunded_amount_cents' => $this->refunded_amount_cents,
            'instructor_ids' => $this->whenLoaded(
                'courseAccess',
                fn () => $this->courseAccess->pluck('instructor_id')->unique()->values()
            ),
        ];
    }
}
