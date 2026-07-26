<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // 1. Tambahkan ini
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class StudyGroup extends Model
{
    use HasUuids, SoftDeletes; // 2. Gunakan ini

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'invite_code',
        'max_members',
        'min_level',
        'job_id',
    ];

    protected $casts = [
        'max_members' => 'integer',
        'min_level' => 'integer',
        'job_id' => 'integer',
    ];

    // 3. Beritahu Laravel kolom mana yang berisi UUID otomatis
    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot(['role', 'deleted_at'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'study_group_staff', 'study_group_id', 'user_id')
            ->withPivot(['role_in_group', 'permissions', 'assigned_by'])
            ->withTimestamps();
    }

    public function staffAccesses()
    {
        return $this->hasMany(StudyGroupStaff::class);
    }

    public function usersWithArchivedMemberships()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot(['role', 'deleted_at'])
            ->withTimestamps();
    }

    public function attachOrRestoreMember(int $userId, string $role = 'member'): void
    {
        $existingPivot = DB::table('group_user')
            ->where('study_group_id', $this->id)
            ->where('user_id', $userId)
            ->first();

        if ($existingPivot) {
            DB::table('group_user')
                ->where('id', $existingPivot->id)
                ->update([
                    'role' => $role,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('group_user')->insert([
            'user_id' => $userId,
            'study_group_id' => $this->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    public function softRemoveMember(int $userId): void
    {
        DB::table('group_user')
            ->where('study_group_id', $this->id)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function quests()
    {
        return $this->hasMany(Quest::class);
    }

    public function joinRequests()
    {
        return $this->hasMany(StudyGroupJoinRequest::class);
    }

    public function job()
    {
        return $this->belongsTo(JobRole::class, 'job_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function roadmaps()
    {
        return $this->belongsToMany(DoopLabRoadmap::class, 'study_group_roadmaps', 'study_group_id', 'roadmap_id')
            ->withPivot(['assigned_by_user_id', 'sort_order', 'is_active'])
            ->withTimestamps()
            ->orderBy('study_group_roadmaps.sort_order')
            ->orderBy('study_group_roadmaps.id');
    }
}
