<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnswerSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_payload',
        'exam_id',
        'user_id',
        'created_by',
        'scanned_by',
        'image_path',
        'scanned_data',
        'total_score',
        'status',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_data' => 'array',
        'scanned_at' => 'datetime',
        'scanned_by' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
