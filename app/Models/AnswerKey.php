<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnswerKey extends Model
{
    use HasFactory;

    // app/Models/AnswerKey.php

    protected $fillable = ['user_id', 'exam_id', 'exam_subject_id', 'answers'];

    protected $casts = [
        'answers' => 'array', // This is vital for the 'store' and 'update' methods
    ];

    public function examSubject()
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
