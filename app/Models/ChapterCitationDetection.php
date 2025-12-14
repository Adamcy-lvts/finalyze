<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterCitationDetection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'chapter_id',
        'claims',
        'total_claims',
        'words_used',
        'detected_at',
    ];

    protected $casts = [
        'claims' => 'array',
        'total_claims' => 'integer',
        'words_used' => 'integer',
        'detected_at' => 'datetime',
    ];

    /**
     * Get the user who created this detection
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project this detection belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the chapter this detection belongs to
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Get the most recent detection for a chapter by user
     */
    public static function getLatestForChapter(int $chapterId, ?int $userId = null): ?self
    {
        $query = self::where('chapter_id', $chapterId);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->orderBy('detected_at', 'desc')->first();
    }
}
