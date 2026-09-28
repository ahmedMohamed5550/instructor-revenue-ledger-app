<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function apiResponse(string $message, int $status = 200, $data = []): JsonResponse
    {
        $response = [
            'message' => $message,
            'status_code' => $status,
            'data' => $data,
        ];

        return response()->json($response, $status);
    }
}
