<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefensePreparation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'executive_briefing',
        'presentation_guide',
        'opening_statement',
        'opening_analysis',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
