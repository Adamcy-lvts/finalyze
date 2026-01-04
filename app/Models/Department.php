<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Department extends Model
{
    protected $fillable = [
        'faculty_id',
        'name',
        'slug',
        'code',
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
     * Get the primary faculty this department belongs to (legacy relationship)
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * Get all faculties this department belongs to (many-to-many)
     * Allows departments like "Mass Communication" to belong to multiple faculties
     */
    public function faculties(): BelongsToMany
    {
        return $this->belongsToMany(Faculty::class, 'department_faculty')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary faculty for this department
     */
    public function primaryFaculty(): ?Faculty
    {
        return $this->faculties()->wherePivot('is_primary', true)->first()
            ?? $this->faculty;
    }

    /**
     * Get all projects in this department
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Scope to get only active departments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get departments by faculty (includes pivot table)
     */
    public function scopeByFaculty($query, int $facultyId)
    {
        return $query->where(function ($q) use ($facultyId) {
            // Check legacy faculty_id column
            $q->where('faculty_id', $facultyId);

            // Or check pivot table if it exists
            if (Schema::hasTable('department_faculty')) {
                $q->orWhereHas('faculties', function ($q2) use ($facultyId) {
                    $q2->where('faculties.id', $facultyId);
                });
            }
        });
    }

    /**
     * Sync faculties for this department
     */
    public function syncFaculties(array $facultyIds, ?int $primaryFacultyId = null): void
    {
        $syncData = [];
        foreach ($facultyIds as $facultyId) {
            // Cast to int for consistent comparison
            $facultyIdInt = (int) $facultyId;
            $syncData[$facultyIdInt] = ['is_primary' => $facultyIdInt === $primaryFacultyId];
        }

        // DEBUG: Log sync data
        \Log::info('Department syncFaculties', [
            'department_id' => $this->id,
            'faculty_ids_input' => $facultyIds,
            'primary_faculty_id' => $primaryFacultyId,
            'sync_data' => $syncData,
        ]);

        $this->faculties()->sync($syncData);

        // Also update legacy faculty_id for backwards compatibility
        if ($primaryFacultyId) {
            $this->update(['faculty_id' => $primaryFacultyId]);
        }
    }
}
