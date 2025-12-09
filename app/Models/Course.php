<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'teacher_id', 'subject_id', 'title', 'description', 'thumbnail_url', 'old_price', 'price', 'is_published', 'enrollment_count', 'processing_status', 'is_main'
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class, 'course_id')->orderBy('order_index');
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
