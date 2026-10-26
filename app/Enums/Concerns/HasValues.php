<?php

namespace App\Enums\Concerns;

trait HasValues
{
    /**
     * Get all backed values defined by the enum.
     *
     * @return list<int|string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
