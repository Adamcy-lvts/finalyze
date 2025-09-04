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
