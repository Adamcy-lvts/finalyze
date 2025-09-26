<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Chapter extends Model
{
    protected $fillable = [
        'project_id', 'chapter_number', 'title', 'slug', 'content',
        'status', 'word_count', 'target_word_count',
        'outline', 'summary', 'version',
    ];

    protected $casts = [
        'outline' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function documentCitations()
    {
        return $this->hasMany(DocumentCitation::class);
    }

    public function verifiedCitations()
    {
        return $this->documentCitations()
            ->whereHas('citation', function ($query) {
                $query->where('verification_status', 'verified');
            });
    }

    public function unverifiedCitations()
    {
        return $this->documentCitations()
            ->where(function ($query) {
                $query->whereDoesntHave('citation')
                    ->orWhereHas('citation', function ($subQuery) {
                        $subQuery->where('verification_status', '!=', 'verified');
                    });
            });
    }

    public function analysisResults()
    {
        return $this->hasMany(ChapterAnalysisResult::class);
    }

    public function latestAnalysis()
    {
        return $this->hasOne(ChapterAnalysisResult::class)->latest('analyzed_at');
    }

    public function updateWordCount()
    {
        $this->word_count = str_word_count(strip_tags($this->content));
        $this->save();
    }

    public function getProgressPercentage()
    {
        if ($this->target_word_count == 0) {
            return 0;
        }

        return min(100, ($this->word_count / $this->target_word_count) * 100);
    }

    public function isComplete()
    {
        return $this->status === 'approved';
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Retrieve the model for a bound value.
     * This ensures chapters are resolved within the context of their project.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Get the project from the route parameters
        $project = request()->route('project');

        if ($project instanceof Project) {
            // Find the chapter by slug within this specific project
            return $this->where('project_id', $project->id)
                        ->where($field ?? $this->getRouteKeyName(), $value)
                        ->first();
        }

        // Fallback to default behavior if no project context
        return parent::resolveRouteBinding($value, $field);
    }

    /**
     * Generate a unique slug for the chapter within its project
     */
    public function generateSlug(): string
    {
        $baseSlug = Str::slug($this->title ?: 'chapter-'.$this->chapter_number);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('project_id', $this->project_id)
            ->where('slug', $slug)
            ->where('id', '!=', $this->id)
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($chapter) {
            if (empty($chapter->slug)) {
                $chapter->slug = $chapter->generateSlug();
            }
        });

        static::updating(function ($chapter) {
            if ($chapter->isDirty('title') && empty($chapter->slug)) {
                $chapter->slug = $chapter->generateSlug();
            }
        });
    }
}
