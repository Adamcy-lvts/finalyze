<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'status',
        'current_stage',
        'progress',
        'message',
        'details',
        'metadata',
    ];

    protected $casts = [
        'details' => 'array',
        'metadata' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
