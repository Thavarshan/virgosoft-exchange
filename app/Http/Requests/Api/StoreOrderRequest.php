<?php

namespace App\Http\Requests\Api;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'side' => [
                'required',
                Rule::enum(OrderSide::class),
            ],
            // Match decimal(20, 2): 18 integer and 2 fractional digits.
            'price' => [
                'required',
                'string',
                'regex:/^\d{1,18}(?:\.\d{1,2})?$/',
                'decimal:0,2',
                'gt:0',
            ],
            // Match decimal(20, 8): 12 integer and 8 fractional digits.
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,12}(?:\.\d{1,8})?$/',
                'decimal:0,8',
                'gt:0',
            ],
        ];
    }

    /**
     * Get the validated order data with decimal strings preserved.
     *
     * @return array{symbol: string, side: string, price: numeric-string, amount: numeric-string}
     */
    public function orderData(): array
    {
        $validated = $this->validated();
        $symbol = $validated['symbol'] ?? null;
        $side = $validated['side'] ?? null;
        $price = $validated['price'] ?? null;
        $amount = $validated['amount'] ?? null;

        if (
            ! is_string($symbol)
            || ! is_string($side)
            || ! is_string($price)
            || ! is_numeric($price)
            || ! is_string($amount)
            || ! is_numeric($amount)
        ) {
            throw new \LogicException('The order data is not valid.');
        }

        return [
            'symbol' => $symbol,
            'side' => $side,
            'price' => $price,
            'amount' => $amount,
        ];
    }
}
