<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = [
        'Exam_Title',
        'Exam_Type',
        'description',
        'program_id',
        'created_by'
    ];

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function answerKeys()
    {
        return $this->hasMany(AnswerKey::class);
    }

    public function examSubject()
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function examSubjects()
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
