<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Keep dashboard data under a named top-level envelope.
     *
     * @var string
     */
    public static $wrap = 'dashboard';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $dashboard */
        $dashboard = $this->resource;

        return $dashboard;
    }
}
