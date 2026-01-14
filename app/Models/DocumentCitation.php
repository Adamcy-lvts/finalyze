<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCitation extends Model
{
    protected $fillable = [
        'document_id',
        'chapter_id',
        'citation_id',
        'collected_paper_id',
        'user_id',
        'inline_text',
        'format_style',
        'position',
        'source',
        'user_approved',
        'needs_review',
        'is_whitelisted',
        'whitelist_key',
        'raw_citation',
        'placeholder_data',
    ];

    protected $casts = [
        'user_approved' => 'boolean',
        'needs_review' => 'boolean',
        'is_whitelisted' => 'boolean',
        'placeholder_data' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'document_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function citation(): BelongsTo
    {
        return $this->belongsTo(Citation::class);
    }

    public function collectedPaper(): BelongsTo
    {
        return $this->belongsTo(CollectedPaper::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerifiedAndApproved(): bool
    {
        return $this->citation_id !== null &&
               $this->user_approved &&
               $this->citation?->verification_status === 'verified';
    }

    public function isPlaceholder(): bool
    {
        return $this->citation_id === null && ! empty($this->raw_citation);
    }

    public function getFormattedText(?string $style = null): string
    {
        $citationStyle = $style ?: $this->format_style;

        if ($this->citation) {
            return $this->citation->getFormattedCitation($citationStyle);
        }

        return $this->raw_citation ?: '[CITATION NEEDED - REQUIRES VERIFICATION]';
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('needs_review', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('user_approved', true);
    }

    public function scopeAiGenerated($query)
    {
        return $query->where('source', 'ai_generated');
    }

    public function scopePlaceholders($query)
    {
        return $query->whereNull('citation_id')
            ->whereNotNull('raw_citation');
    }
}
