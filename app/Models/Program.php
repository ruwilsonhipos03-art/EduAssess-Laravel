<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'Program_Name',
        'college_id',
        'department_id',
    ];

    private function orgUnitForeignKey(): string
    {
        if (Schema::hasColumn('programs', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    public function college()
    {
        return $this->belongsTo(College::class, $this->orgUnitForeignKey());
    }

    public function department()
    {
        return $this->college();
    }

    public function requirement()
    {
        return $this->hasOne(ProgramRequirement::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
