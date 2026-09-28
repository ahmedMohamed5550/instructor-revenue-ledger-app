<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['instructor_id', 'title'];

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
