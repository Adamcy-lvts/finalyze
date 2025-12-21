<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestTopicDownload extends Model
{
    protected $fillable = [
        'project_topic_id',
        'student_name',
        'email',
        'university',
        'faculty',
        'department',
        'course',
        'matric_no',
        'academic_level',
        'ip_address',
    ];

    public function projectTopic()
    {
        return $this->belongsTo(ProjectTopic::class);
    }
}
