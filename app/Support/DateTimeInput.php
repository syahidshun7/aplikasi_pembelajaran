<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

class DateTimeInput
{
    public static function normalizeNullable(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timezone = config('app.timezone', 'UTC');

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance(Carbon::parse($value))
                ->setTimezone($timezone)
                ->format('Y-m-d H:i:s');
        }

        $formats = [
            'Y-m-d\TH:i',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value, $timezone)
                    ->format('Y-m-d H:i:s');
            } catch (Throwable) {
                // Fallback to generic parsing below.
            }
        }

        return Carbon::parse($value, $timezone)
            ->setTimezone($timezone)
            ->format('Y-m-d H:i:s');
    }
}
