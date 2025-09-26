<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CitationCache extends Model
{
    protected $table = 'citation_cache';

    protected $fillable = [
        'cache_key',
        'api_source',
        'response_data',
        'hits',
        'expires_at',
    ];

    protected $casts = [
        'response_data' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Retrieve cached response for API call
     */
    public static function retrieve(string $apiName, string $endpoint, array $params = []): ?array
    {
        $cacheKey = self::generateKey($apiName, $endpoint, $params);

        $cached = self::where('cache_key', $cacheKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            // Increment hit counter
            $cached->increment('hits');

            return $cached->response_data;
        }

        return null;
    }

    /**
     * Store API response in cache
     */
    public static function store(string $apiName, string $endpoint, array $data, array $params = [], int $hours = 720): void
    {
        $cacheKey = self::generateKey($apiName, $endpoint, $params);

        self::updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'api_source' => $apiName,
                'response_data' => $data,
                'expires_at' => now()->addHours($hours),
                'hits' => 1,
            ]
        );
    }

    /**
     * Generate unique cache key
     */
    public static function generateKey(string $apiName, string $endpoint, array $params = []): string
    {
        // Sort parameters for consistent key generation
        ksort($params);
        $paramString = http_build_query($params);

        return 'citation_api_'.md5($apiName.':'.$endpoint.':'.$paramString);
    }

    /**
     * Clean expired cache entries
     */
    public static function cleanExpired(): int
    {
        return self::where('expires_at', '<=', now())->delete();
    }

    /**
     * Get cache statistics
     */
    public static function getStats(): array
    {
        return [
            'total_entries' => self::count(),
            'expired_entries' => self::where('expires_at', '<=', now())->count(),
            'active_entries' => self::where('expires_at', '>', now())->count(),
            'total_hits' => self::sum('hits'),
            'by_api' => self::selectRaw('api_source, COUNT(*) as count, SUM(hits) as total_hits')
                ->groupBy('api_source')
                ->get()
                ->keyBy('api_source')
                ->toArray(),
        ];
    }
}
