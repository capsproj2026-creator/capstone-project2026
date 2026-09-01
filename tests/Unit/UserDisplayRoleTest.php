<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserRole;
use App\Services\TemporaryRfidService;
use Tests\TestCase;

class UserDisplayRoleTest extends TestCase
{
    public function test_staff_role_displays_as_faculty(): void
    {
        $user = new User(['fullname' => 'Jane Faculty']);
        $user->setRelation('role', new UserRole(['role_name' => 'Staff']));

        $this->assertSame('Faculty', $user->displayRoleLabel());
        $this->assertSame('Faculty', $user->gateRoleLabel());
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
