<?php

namespace App\Http\Requests\Api;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderHistoryRequest extends FormRequest
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
     * Query values are validated against the same enums used by the order
     * model, while numeric status values remain strings at the HTTP boundary.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'symbol' => [
                'sometimes',
                'string',
                Rule::in(AssetSymbol::all()),
            ],
            'side' => [
                'sometimes',
                'string',
                Rule::in(OrderSide::all()),
            ],
            'status' => [
                'sometimes',
                'integer',
                Rule::in(array_map(
                    static fn (int|string $value): string => (string) $value,
                    OrderStatus::all(),
                )),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the requested market symbol, if one was supplied.
     */
    public function symbol(): ?AssetSymbol
    {
        return $this->filled('symbol')
            ? AssetSymbol::from($this->string('symbol')->toString())
            : null;
    }

    /**
     * Get the requested order side, if one was supplied.
     */
    public function side(): ?OrderSide
    {
        return $this->filled('side')
            ? OrderSide::from($this->string('side')->toString())
            : null;
    }

    /**
     * Get the requested order status, if one was supplied.
     */
    public function status(): ?OrderStatus
    {
        return $this->filled('status')
            ? OrderStatus::from($this->integer('status'))
            : null;
    }
}
