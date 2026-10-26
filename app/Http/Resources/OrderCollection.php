<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/**
 * @property-read Collection<int, OrderResource> $collection
 */
class OrderCollection extends ResourceCollection
{
    /**
     * Wrap order lists with the API's established orders key.
     *
     * @var string
     */
    public static $wrap = 'orders';

    /**
     * Serialize each item through the shared order resource.
     *
     * @var class-string<OrderResource>
     */
    public $collects = OrderResource::class;
}
