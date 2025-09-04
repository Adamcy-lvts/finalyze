<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMetadata extends Model
{
    protected $fillable = [
        'project_id',
        'academic_session',
        'matriculation_number',
        'department',
        'faculty',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
