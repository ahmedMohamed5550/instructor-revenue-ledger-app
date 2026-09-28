<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefundSubscriptionRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;

/**
 * Deliberately thin: validate (via Form Request), delegate to the Service,
 * shape the response (via API Resource). No business logic lives here -
 * anything that touches money or the ledger belongs in SubscriptionService
 * or the Actions it calls, where it's covered by unit/feature tests
 * independent of HTTP concerns.
 */
class SubscriptionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private SubscriptionService $subscriptions){}

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->subscriptions->purchase(
            User::findOrFail($request->validated('student_id')),
            SubscriptionPlan::findOrFail($request->validated('plan_id')),
            $request->validated('course_ids'),
        );

        return $this->apiResponse('Subscription purchased successfully', 201, new SubscriptionResource($subscription));
    }

    public function refund(RefundSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $asOf = $request->validated('as_of')
            ? Carbon::parse($request->validated('as_of'))
            : null;

        return new SubscriptionResource(
            $this->subscriptions->refund($subscription, $asOf)
        );
    }
}
