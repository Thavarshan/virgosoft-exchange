<?php

namespace App\Actions\Fortify;

use App\Actions\Wallet\InitializeWallet;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\StartingWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Create registrations with their starting wallet in one transaction.
     */
    public function __construct(
        protected readonly InitializeWallet $initializeWallet,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->initializeWallet->execute(
                user: $user,
                usdBalance: StartingWallet::USD_BALANCE,
                assetBalances: StartingWallet::ASSET_BALANCES,
                initializeUsdBalance: true,
            );

            return $user;
        });
    }
}
