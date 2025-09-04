<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProjectCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'academic_levels',
        'description',
        'default_chapter_count',
        'chapter_structure',
        'target_word_count',
        'target_duration',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'academic_levels' => 'array',
        'chapter_structure' => 'array',
        'is_active' => 'boolean',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAcademicLevel($query, string $level)
    {
        return $query->whereJsonContains('academic_levels', $level);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
