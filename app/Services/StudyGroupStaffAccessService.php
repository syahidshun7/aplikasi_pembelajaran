<?php

namespace App\Services;

use App\Models\StudyGroup;
use App\Models\User;

class StudyGroupStaffAccessService
{
    public function canAccess(?User $user, StudyGroup $group): bool
    {
        if (! $user || ! $user->isStaff()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $group->staffAccesses()
            ->where('user_id', (int) $user->id)
            ->exists();
    }

    public function scopeAccessibleGroups($query, ?User $user)
    {
        if (! $user || ! $user->isStaff()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('staffAccesses', function ($staffQuery) use ($user) {
            $staffQuery->where('user_id', (int) $user->id);
        });
    }
}
