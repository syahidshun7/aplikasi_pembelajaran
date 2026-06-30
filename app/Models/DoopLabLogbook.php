<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabLogbook extends Model
{
    protected $table = 'dooplab_logbooks';

    protected $fillable = ['uuid', 'owner_user_id', 'title', 'description'];

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            if (empty($m->uuid)) $m->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }

    /** Semua anggota logbook (members + mentors) via pivot. */
    public function membersAll()
    {
        return $this->belongsToMany(User::class, 'dooplab_logbook_members', 'logbook_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Hanya users dengan role 'member'. */
    public function members()
    {
        return $this->belongsToMany(User::class, 'dooplab_logbook_members', 'logbook_id', 'user_id')
            ->withPivot('role')
            ->wherePivot('role', 'member');
    }

    /** Hanya users dengan role 'mentor'. */
    public function mentors()
    {
        return $this->belongsToMany(User::class, 'dooplab_logbook_members', 'logbook_id', 'user_id')
            ->withPivot('role')
            ->wherePivot('role', 'mentor');
    }

    public function entries()
    {
        return $this->hasMany(DoopLabLogbookEntry::class, 'logbook_id')->latest('activity_date')->latest('id');
    }

    public function canEditBy(User $user): bool
    {
        if ($user->isAdmin()) return true;
        if ((int) $this->owner_user_id === (int) $user->id) return true;

        // Cek pivot: member atau mentor bisa edit entri
        return $this->membersAll()->where('users.id', $user->id)->exists();
    }

    public function canDeleteBy(User $user): bool
    {
        if ($user->isAdmin()) return true;
        // Hanya owner yang boleh hapus
        return (int) $this->owner_user_id === (int) $user->id;
    }
}
