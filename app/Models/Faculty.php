<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    protected $fillable = [
        'faculty_structure_id',
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the faculty structure template for this faculty
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(FacultyStructure::class, 'faculty_structure_id');
    }

    /**
     * Get all departments in this faculty
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get all projects in this faculty
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Scope to get only active faculties
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
