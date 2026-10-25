<?php

namespace App\Http\Requests\Api;

use App\Enums\AssetSymbol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends FormRequest
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
        return [
            'symbol' => ['required', Rule::enum(AssetSymbol::class)],
        ];
    }

    /**
     * Get the requested market symbol as its backed enum.
     */
    public function symbol(): AssetSymbol
    {
        return AssetSymbol::from((string) $this->validated('symbol'));
    }
}
