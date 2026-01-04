<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * Get all departments in this faculty (legacy HasMany relationship)
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get all departments belonging to this faculty (many-to-many)
     * This includes departments that belong to multiple faculties
     */
    public function allDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_faculty')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get departments for this faculty (combines both legacy and pivot relationships)
     */
    public function getDepartmentsForSelection()
    {
        // Get department IDs from pivot table
        $pivotDepartmentIds = $this->allDepartments()->pluck('departments.id');

        // Get department IDs from legacy relationship
        $legacyDepartmentIds = $this->departments()->pluck('id');

        // Merge and get unique departments
        $allIds = $pivotDepartmentIds->merge($legacyDepartmentIds)->unique();

        return Department::whereIn('id', $allIds)
            ->active()
            ->orderBy('name')
            ->get();
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
