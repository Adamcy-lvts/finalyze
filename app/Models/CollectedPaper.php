<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectedPaper extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'authors',
        'content_hash',
        'year',
        'venue',
        'doi',
        'url',
        'abstract',
        'citation_count',
        'quality_score',
        'source_api',
        'paper_id',
        'is_open_access',
        'collected_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'citation_count' => 'integer',
        'quality_score' => 'decimal:2',
        'is_open_access' => 'boolean',
        'collected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Generate content hash for uniqueness
     */
    public static function generateContentHash(string $title, string $authors): string
    {
        return md5(strtolower(trim($title)).'|'.strtolower(trim($authors)));
    }

    /**
     * Create or update collected paper with hash-based uniqueness
     */
    public static function createOrUpdatePaper(array $data): self
    {
        $contentHash = self::generateContentHash($data['title'], $data['authors']);

        return self::updateOrCreate(
            [
                'project_id' => $data['project_id'],
                'content_hash' => $contentHash,
            ],
            array_merge($data, ['content_hash' => $contentHash])
        );
    }

    /**
     * Scope for project papers ordered by quality
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId)
            ->orderByDesc('quality_score')
            ->orderByDesc('citation_count');
    }

    /**
     * Scope for recent collections
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('collected_at', '>=', now()->subDays($days));
    }
}
