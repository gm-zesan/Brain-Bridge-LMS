<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'teacher_level_id',
        'title',
        'introduction_video',
        'base_pay',
        'total_sessions',
        'average_rating',
        'five_star_reviews',
        'streak_good_sessions',
        'rebook_count',
        'cancelled_sessions',
        'payment_method',
        'bank_account_number',
        'bank_routing_number',
        'bank_name',
        'paypal_email',
        'stripe_account_id',
        'tax_id',
    ];

    protected $casts = [
        'bank_account_number' => 'encrypted',
        'bank_routing_number' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function teacherLevel()
    {
        return $this->belongsTo(TeacherLevel::class);
    }

    public function videoLessons()
    {
        return $this->hasMany(VideoLesson::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'teacher_skills')
                    ->withPivot('years_of_experience')
                    ->withTimestamps();
    }


    public function availableSlots()
    {
        return $this->hasMany(AvailableSlot::class, 'teacher_id');
    }

    public function sessions()
    {
        return $this->hasMany(LessonSession::class, 'teacher_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function inPersonSlots()
    {
        return $this->hasMany(InPersonSlot::class, 'teacher_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'teacher_id');
    }
}
