<?php

namespace App\Policies;

use App\Models\Rubric;
use App\Models\User;

class RubricPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Rubric $rubric): bool
    {
        return $user->isMentor() && (int) $rubric->mentor_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isMentor();
    }

    public function update(User $user, Rubric $rubric): bool
    {
        return $user->isMentor() && (int) $rubric->mentor_id === (int) $user->id;
    }

    public function delete(User $user, Rubric $rubric): bool
    {
        return $this->update($user, $rubric);
    }
}

