<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;

final class CacheVersion
{
    private function __construct()
    {
    }

    private static function key(string $namespace): string
    {
        $namespace = trim($namespace);
        $namespace = $namespace !== '' ? $namespace : 'default';
        return "cache.version.{$namespace}";
    }

    public static function get(string $namespace): int
    {
        return (int) Cache::get(self::key($namespace), 1);
    }

    public static function bump(string $namespace): int
    {
        $key = self::key($namespace);

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        $newValue = Cache::increment($key);
        if (is_int($newValue)) {
            return $newValue;
        }

        Cache::forever($key, 2);
        return 2;
    }
}

