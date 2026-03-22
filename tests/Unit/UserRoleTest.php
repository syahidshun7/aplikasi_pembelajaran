<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_super_admin_is_treated_as_admin_and_staff(): void
    {
        $user = new User([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isStaff());
        $this->assertFalse($user->isMentor());
    }

    public function test_mentor_remains_scoped_as_non_admin_staff(): void
    {
        $user = new User([
            'role' => User::ROLE_MENTOR,
        ]);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isStaff());
        $this->assertTrue($user->isMentor());
    }
}
