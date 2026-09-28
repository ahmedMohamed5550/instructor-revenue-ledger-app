<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // no auth system in scope for this exercise
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
        ];
    }
}
