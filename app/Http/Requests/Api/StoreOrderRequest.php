<?php

namespace App\Http\Requests\Api;

use App\Actions\Trading\OrderData;
use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

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
     * Get the validated order data as an immutable trading value object.
     */
    public function orderData(): OrderData
    {
        $validated = $this->validated();

        return new OrderData(
            symbol: AssetSymbol::from((string) $validated['symbol']),
            side: OrderSide::from((string) $validated['side']),
            price: $this->numericString($validated['price'] ?? null),
            amount: $this->numericString($validated['amount'] ?? null),
        );
    }

    /**
     * Convert a validated decimal input to the numeric-string type used by
     * BCMath operations.
     *
     * @return numeric-string
     */
    protected function numericString(mixed $value): string
    {
        if (! is_string($value) || ! is_numeric($value)) {
            throw new LogicException('The order data is not valid.');
        }

        return $value;
    }
}
