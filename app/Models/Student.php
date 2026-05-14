<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'applicant_id',
        'Student_Number',
        'program_id',
    ];

    public static function generateApplicantId(): string
    {
        $latest = DB::table('students')
            ->whereNotNull('applicant_id')
            ->where('applicant_id', 'regexp', '^[0-9]+$')
            ->selectRaw('MAX(CAST(applicant_id AS UNSIGNED)) as max_applicant_id')
            ->value('max_applicant_id');

        $next = ((int) ($latest ?? 0)) + 1;
        return str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public static function generateStudentNumber(): string
    {
        $year = (int) now()->year;
        $prefix = $year . '-01-';
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';

        $latest = DB::table('students')
            ->where('Student_Number', 'like', $prefix . '%')
            ->orderBy('Student_Number', 'desc')
            ->value('Student_Number');

        $next = 20001;
        if (is_string($latest) && preg_match($pattern, $latest, $matches)) {
            $current = (int) $matches[1];
            $next = max($current + 1, 20001);
        }

        $suffix = str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        return $prefix . $suffix;
    }
}
