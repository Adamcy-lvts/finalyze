<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChatFileUpload extends Model
{
    protected $fillable = [
        'upload_id',
        'user_id',
        'project_id',
        'chapter_number',
        'session_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'extracted_text',
        'analysis_results',
        'word_count',
        'citations_found',
        'main_topics',
        'status',
        'is_active',
        'last_accessed_at',
    ];

    protected $casts = [
        'analysis_results' => 'array',
        'main_topics' => 'array',
        'is_active' => 'boolean',
        'last_accessed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Accessors
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function getFileExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));
    }

    public function getEstimatedReadingTimeAttribute(): int
    {
        // Average reading speed: 200-250 words per minute
        return max(1, ceil($this->word_count / 225));
    }

    /**
     * Methods
     */
    public function getFileContent(): ?string
    {
        try {
            if (Storage::disk('private')->exists($this->stored_path)) {
                $this->touch('last_accessed_at');

                return $this->extracted_text;
            }
        } catch (\Exception $e) {
            logger()->error('Failed to get file content', [
                'upload_id' => $this->upload_id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function deleteFile(): bool
    {
        try {
            if (Storage::disk('private')->exists($this->stored_path)) {
                Storage::disk('private')->delete($this->stored_path);
            }

            $this->update(['is_active' => false]);

            return true;
        } catch (\Exception $e) {
            logger()->error('Failed to delete file', [
                'upload_id' => $this->upload_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    /**
     * Get summary for chat context
     */
    public function getSummaryForChat(): string
    {
        $summary = "📎 **{$this->original_filename}** ({$this->formatted_file_size})\n";
        $summary .= "📊 {$this->word_count} words, {$this->citations_found} citations\n";

        if (! empty($this->main_topics)) {
            $topics = array_slice($this->main_topics, 0, 5);
            $summary .= '🔍 Key topics: '.implode(', ', $topics)."\n";
        }

        if (isset($this->analysis_results['ai_analysis'])) {
            $analysis = substr($this->analysis_results['ai_analysis'], 0, 200);
            $summary .= "🤖 AI Analysis: {$analysis}...\n";
        }

        return $summary;
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($upload) {
            // Clean up file when model is deleted
            if ($upload->is_active && Storage::disk('private')->exists($upload->stored_path)) {
                Storage::disk('private')->delete($upload->stored_path);
            }
        });
    }
}
