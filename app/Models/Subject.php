<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['Subject_Name'];

    public function examSubjects()
    {
        return $this->hasMany(ExamSubject::class);
    }
}
