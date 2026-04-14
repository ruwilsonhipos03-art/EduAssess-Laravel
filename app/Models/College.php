<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class College extends Model
{
    use HasFactory;

    protected $fillable = [
        'College_Name',
    ];

    public function getTable()
    {
        if (Schema::hasTable('colleges')) {
            return 'colleges';
        }

        if (Schema::hasTable('departments')) {
            return 'departments';
        }

        return parent::getTable();
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
