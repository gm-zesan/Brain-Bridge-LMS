<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\CustomVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'phone',
        'bio',
        'address',
        'date_of_birth',
        'profile_picture',
        'is_active',
        'points',
        'referral_code',
        'referred_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            do {
                $code = strtoupper(Str::random(8));
            } while (User::where('referral_code', $code)->exists());

            $user->referral_code = $code;
            $user->save();
        });
    }



    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }


    public function transaction()
    {
        return $this->hasMany(Transaction::class);
    }

    public function availableSlots()
    {
        return $this->hasMany(AvailableSlot::class, 'teacher_id');
    }

    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class, 'student_id');
    }

    public function referralsMade()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }
}
