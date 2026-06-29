<?php
namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\RoleBasedAccessControl;

class RoleBasedAccessControlTest extends TestCase {
    private $mockDb;
    private $mockLogger;
    private $rbac;

    protected function setUp(): void {
        $this->mockDb = $this->createMock(\App\Database::class);
        $this->mockLogger = $this->createMock(\App\Logger::class);
        $this->rbac = new RoleBasedAccessControl($this->mockDb, $this->mockLogger);
    }

    /**
     * Test hasRole returns true when user has role
     */
    public function testHasRoleReturnsTrueWhenUserHasRole() {
        $user = (object)['id' => 1];

        // Mock getUserRoles to return admin role
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [
                ['id' => 1, 'user_id' => 1, 'role_id' => 1],
            ],
        ]);

        $this->mockDb->method('get')->willReturn([
            'status' => 200,
            'data' => ['id' => 1, 'name' => 'admin'],
        ]);

        $result = $this->rbac->hasRole($user, 'admin');
        $this->assertTrue($result);
    }

    /**
     * Test hasRole returns false when user doesn't have role
     */
    public function testHasRoleReturnsFalseWhenUserDoesntHaveRole() {
        $user = (object)['id' => 1];

        // Mock getUserRoles to return user role
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [
                ['id' => 1, 'user_id' => 1, 'role_id' => 2],
            ],
        ]);

        $this->mockDb->method('get')->willReturn([
            'status' => 200,
            'data' => ['id' => 2, 'name' => 'user'],
        ]);

        $result = $this->rbac->hasRole($user, 'admin');
        $this->assertFalse($result);
    }

    /**
     * Test hasRole with multiple required roles (OR logic)
     */
    public function testHasRoleWithMultipleRoles() {
        $user = (object)['id' => 1];

        // Mock getUserRoles to return user role
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [
                ['id' => 1, 'user_id' => 1, 'role_id' => 2],
            ],
        ]);

        $this->mockDb->method('get')->willReturn([
            'status' => 200,
            'data' => ['id' => 2, 'name' => 'user'],
        ]);

        $result = $this->rbac->hasRole($user, ['admin', 'user', 'guest']);
        $this->assertTrue($result);
    }

    /**
     * Test hasRole returns false when user is null
     */
    public function testHasRoleReturnsFalseWhenUserIsNull() {
        $result = $this->rbac->hasRole(null, 'admin');
        $this->assertFalse($result);
    }

    /**
     * Test assignRole successfully assigns role
     */
    public function testAssignRoleSuccessfully() {
        // Mock user exists
        $this->mockDb->method('get')->will($this->onConsecutiveCalls(
            ['status' => 200, 'data' => ['id' => 1, 'email' => 'test@test.com']],
            ['status' => 200, 'data' => ['id' => 1, 'name' => 'admin']],
        ));

        // Mock role not already assigned
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [],
        ]);

        // Mock execute for insert
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->rbac->assignRole(1, 1);

        $this->assertEquals(200, $result['status']);
        $this->assertStringContainsString('successfully', $result['message']);
    }

    /**
     * Test assignRole fails when role already assigned
     */
    public function testAssignRoleFailsWhenRoleAlreadyAssigned() {
        // Mock user exists
        $this->mockDb->method('get')->will($this->onConsecutiveCalls(
            ['status' => 200, 'data' => ['id' => 1, 'email' => 'test@test.com']],
            ['status' => 200, 'data' => ['id' => 1, 'name' => 'admin']],
        ));

        // Mock role already assigned
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [
                ['id' => 1, 'user_id' => 1, 'role_id' => 1],
            ],
        ]);

        $result = $this->rbac->assignRole(1, 1);

        $this->assertEquals(400, $result['status']);
        $this->assertStringContainsString('already has this role', $result['message']);
    }

    /**
     * Test assignRole fails when user not found
     */
    public function testAssignRoleFailsWhenUserNotFound() {
        // Mock user not found
        $this->mockDb->method('get')->willReturn([
            'status' => 404,
            'data' => null,
        ]);

        $result = $this->rbac->assignRole(999, 1);

        $this->assertEquals(404, $result['status']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    /**
     * Test removeRole successfully removes role
     */
    public function testRemoveRoleSuccessfully() {
        // Mock role is assigned
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [
                ['id' => 1, 'user_id' => 1, 'role_id' => 1],
            ],
        ]);

        // Mock execute for delete
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->rbac->removeRole(1, 1);

        $this->assertEquals(200, $result['status']);
        $this->assertStringContainsString('successfully', $result['message']);
    }

    /**
     * Test removeRole fails when role not assigned
     */
    public function testRemoveRoleFailsWhenRoleNotAssigned() {
        // Mock role is not assigned
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [],
        ]);

        $result = $this->rbac->removeRole(1, 1);

        $this->assertEquals(404, $result['status']);
        $this->assertStringContainsString('does not have this role', $result['message']);
    }

    /**
     * Test isOwner returns true when user is owner
     */
    public function testIsOwnerReturnsTrueWhenUserIsOwner() {
        $result = $this->rbac->isOwner(1, 'users', 1);
        $this->assertTrue($result);
    }

    /**
     * Test isOwner returns false when user is not owner
     */
    public function testIsOwnerReturnsFalseWhenUserIsNotOwner() {
        $result = $this->rbac->isOwner(1, 'users', 2);
        $this->assertFalse($result);
    }

    /**
     * Test isOwner returns false for non-users table
     */
    public function testIsOwnerReturnsFalseForNonUsersTable() {
        $result = $this->rbac->isOwner(1, 'items', 1);
        $this->assertFalse($result);
    }
}
