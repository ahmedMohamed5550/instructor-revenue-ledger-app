<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionCourseAccess extends Model
{
    protected $table = 'subscription_course_access';
    protected $fillable = ['subscription_id', 'course_id', 'instructor_id'];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
