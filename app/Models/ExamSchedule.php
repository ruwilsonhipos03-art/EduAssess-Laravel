<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'time',
        'location',
        'schedule_name',
        'capacity',
        'schedule_type',
    ];
}
