<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserRole;
use App\Services\TemporaryRfidService;
use Tests\TestCase;

class UserDisplayRoleTest extends TestCase
{
    public function test_staff_role_displays_as_teaching_or_non_teaching_staff(): void
    {
        $user = new User(['fullname' => 'Jane Faculty']);
        $user->setRelation('role', new UserRole(['role_name' => 'Staff']));

        $this->assertSame(User::STAFF_DISPLAY_LABEL, $user->displayRoleLabel());
        $this->assertSame(User::STAFF_DISPLAY_LABEL, $user->gateRoleLabel());
        $this->assertSame('Staff', $user->roleName());
    }

    public function test_unregistered_placeholder_stays_student_faculty(): void
    {
        $user = new User([
            'fullname' => TemporaryRfidService::PLACEHOLDER_NAME,
            'account_type' => TemporaryRfidService::ACCOUNT_TEMPORARY,
        ]);
        $user->setRelation('role', new UserRole(['role_name' => 'Student']));

        $this->assertSame('Student / Faculty', $user->displayRoleLabel());
        $this->assertSame('Student / Faculty', $user->gateRoleLabel());
    }
}
