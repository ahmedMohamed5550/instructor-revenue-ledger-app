<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use Notifiable;
    use HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function earnings()
    {
        return $this->hasMany(InstructorEarning::class, 'instructor_id');
    }

    public function payouts()
    {
        return $this->hasMany(InstructorPayout::class, 'instructor_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'student_id');
    }
}
