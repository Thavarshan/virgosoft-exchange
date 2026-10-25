<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'balance' => $this->balance,
            'locked_balance' => $this->locked_balance,
            'assets' => $this->assets->map(
                static function (Asset $asset): array {
                    return [
                        'symbol' => $asset->symbol->value,
                        'amount' => $asset->amount,
                        'locked_amount' => $asset->locked_amount,
                    ];
                }
            )->values()->all(),
        ];
    }
}
