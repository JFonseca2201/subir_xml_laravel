<?php

namespace App\Traits;

use DateTimeInterface;

trait SerializeDateLocal
{
    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        // Format the date in the local timezone (already set on Carbon instance)
        return $date->format('Y-m-d H:i:s');
    }
}
