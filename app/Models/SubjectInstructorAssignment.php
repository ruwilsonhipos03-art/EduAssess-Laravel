<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubjectInstructorAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'instructor_user_id',
        'assigned_by_user_id',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}

