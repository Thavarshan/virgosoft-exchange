<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * Reject deletion while financial records or balances still exist.
     *
     * Financial rows intentionally retain their owning user, so deletion is
     * safer to reject than to cascade away trading history.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            $user->refresh();

            $hasBalances = $user->balance !== '0.00'
                || $user->locked_balance !== '0.00';
            $hasTradingRecords = $user->assets()->exists()
                || $user->orders()->exists();

            if ($hasBalances || $hasTradingRecords) {
                $validator->errors()->add(
                    'account',
                    'Accounts with balances or trading records cannot be deleted.',
                );
            }
        }];
    }
}
