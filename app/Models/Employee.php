<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'Employee_Number',
        'college_id',
        'department_id',
        'office_id',
        'program_id',
    ];

    private function collegeForeignKey(): string
    {
        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class, $this->collegeForeignKey());
    }

    public function department()
    {
        return $this->college();
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
