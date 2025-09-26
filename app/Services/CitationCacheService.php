<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CitationCacheService
{
    private string $prefix = 'citation_cache:';

    public function get(string $key): ?array
    {
        return Cache::get($this->prefix.$key);
    }

    public function put(string $key, array $data, int $ttlSeconds): void
    {
        Cache::put($this->prefix.$key, $data, $ttlSeconds);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->prefix.$key);
    }

    public function flush(): bool
    {
        // Get all cache keys with our prefix and delete them
        $keys = Cache::getRedis()->keys($this->prefix.'*');

        if (! empty($keys)) {
            return Cache::getRedis()->del($keys) > 0;
        }

        return true;
    }
}
