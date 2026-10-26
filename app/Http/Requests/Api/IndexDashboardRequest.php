<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDashboardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return ['period' => ['sometimes', 'integer', Rule::in([7, 30])]];
    }

    /**
     * Get the validated reporting period in calendar days.
     */
    public function period(): int
    {
        return (int) ($this->validated('period') ?? 7);
    }
}
