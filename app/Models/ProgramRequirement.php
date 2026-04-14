<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'total_score',
        'math_scale',
        'english_scale',
        'science_scale',
        'social_science_scale',
    ];

    protected $casts = [
        'program_id' => 'integer',
        'total_score' => 'integer',
        'math_scale' => 'float',
        'english_scale' => 'float',
        'science_scale' => 'float',
        'social_science_scale' => 'float',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
