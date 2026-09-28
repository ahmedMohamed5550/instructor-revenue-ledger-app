<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'as_of' => ['sometimes', 'date'],
        ];
    }
}
