<?php

namespace App\Models;

use App\Notifications\Auth\CustomResetPassword;
use App\Notifications\Auth\CustomVerifyEmail;
use App\Models\StudyGroup;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'job_id',
        'profile_photo',
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
        ];
    }


    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

public function studyGroups()
{
    return $this->belongsToMany(StudyGroup::class, 'group_user', 'user_id', 'study_group_id')
                ->withPivot('role')
                ->withTimestamps();
}

public function job()
{
    return $this->belongsTo(JobRole::class, 'job_id');
}

public function eventAttendances()
{
    return $this->hasMany(EventAttendance::class);
}

public function inventories()
{
    return $this->hasMany(UserInventory::class);
}

public function shopTransactions()
{
    return $this->hasMany(ShopTransaction::class);
}

public function sendEmailVerificationNotification(): void
{
    $this->notify(new CustomVerifyEmail());
}

public function sendPasswordResetNotification($token): void
{
    $this->notify(new CustomResetPassword($token));
}
}
