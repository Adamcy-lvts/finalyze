<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'type',
        'message',
        'subject_type',
        'subject_id',
        'causer_id',
        'metadata',
        'ip',
        'user_agent',
        'route',
        'method',
        'url',
        'status_code',
        'duration_ms',
        'request_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'subject_id' => 'integer',
        'causer_id' => 'integer',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public static function record(
        string $type,
        string $message,
        ?Model $subject = null,
        User|int|null $causer = null,
        array $metadata = []
    ): self {
        $req = request();
        $causerId = is_int($causer) ? $causer : $causer?->id;
        $routeName = method_exists($req, 'route') ? $req->route()?->getName() : null;
        $requestMethod = method_exists($req, 'method') ? $req->method() : null;
        $requestUrl = method_exists($req, 'fullUrl') ? $req->fullUrl() : null;
        $requestId = method_exists($req, 'header') ? $req->header('X-Request-Id') : null;
        $statusCode = $metadata['status_code'] ?? data_get($metadata, 'response.status');
        $durationMs = $metadata['duration_ms'] ?? null;

        return self::create([
            'type' => $type,
            'message' => $message,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject ? $subject->getKey() : null,
            'causer_id' => $causerId,
            'metadata' => $metadata ?: null,
            'ip' => method_exists($req, 'ip') ? $req->ip() : null,
            'user_agent' => method_exists($req, 'userAgent') ? (string) $req->userAgent() : null,
            'route' => $metadata['route'] ?? $routeName,
            'method' => $metadata['method'] ?? $requestMethod,
            'url' => $metadata['url'] ?? $requestUrl,
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'request_id' => $requestId,
        ]);
    }
}
