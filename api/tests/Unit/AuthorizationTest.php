<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AuthorizationTest extends TestCase
{
    /**
     * Test that permission checker class exists
     */
    public function testPermissionCheckerExists(): void
    {
        $this->assertTrue(class_exists('App\PermissionChecker'));
    }

    /**
     * Test that permissions config file exists
     */
    public function testPermissionsConfigExists(): void
    {
        $permissionsFile = __DIR__ . '/../../config/permissions.php';
        $this->assertFileExists($permissionsFile);

        $permissions = require $permissionsFile;
        $this->assertIsArray($permissions);
        $this->assertArrayHasKey('default', $permissions);
    }

    /**
     * Test that permission matrix is defined for key tables
     */
    public function testPermissionMatrixDefined(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';

        // Check key tables
        $this->assertArrayHasKey('users', $permissions);
        $this->assertArrayHasKey('categories', $permissions);
        $this->assertArrayHasKey('items', $permissions);
    }

    /**
     * Test that roles have all CRUD permissions defined
     */
    public function testRolesHaveCrudPermissions(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';

        $crudOps = ['read', 'create', 'update', 'delete'];
        $roles = ['admin', 'user', 'guest'];

        foreach ($permissions as $table => $config) {
            if ($table === 'default') continue;

            $tablePerms = $config['permissions'];
            foreach ($roles as $role) {
                $this->assertArrayHasKey($role, $tablePerms);
                foreach ($crudOps as $op) {
                    $this->assertArrayHasKey($op, $tablePerms[$role]);
                }
            }
        }
    }

    /**
     * Test that ownership restrictions are defined
     */
    public function testOwnershipRestrictionsExist(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';

        $ownerOps = ['read_own_only', 'update_own_only', 'delete_own_only'];
        $roles = ['admin', 'user', 'guest'];

        foreach ($permissions as $table => $config) {
            if ($table === 'default') continue;

            foreach ($roles as $role) {
                $rolePerms = $config['permissions'][$role] ?? null;
                if ($rolePerms) {
                    foreach ($ownerOps as $op) {
                        $this->assertArrayHasKey($op, $rolePerms);
                    }
                }
            }
        }
    }

    /**
     * Test that admin has full access
     */
    public function testAdminHasFullAccess(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';

        $crudOps = ['read', 'create', 'update', 'delete'];

        foreach ($permissions as $table => $config) {
            if ($table === 'default') continue;

            $adminPerms = $config['permissions']['admin'];
            foreach ($crudOps as $op) {
                $this->assertTrue($adminPerms[$op], "Admin should have $op on $table");
                if (isset($adminPerms["{$op}_own_only"])) {
                    $this->assertFalse($adminPerms["{$op}_own_only"], "Admin should not have {$op}_own_only restriction on $table");
                }
            }
        }
    }

    /**
     * Test that guest has limited access
     */
    public function testGuestHasLimitedAccess(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';

        foreach ($permissions as $table => $config) {
            if ($table === 'default') continue;

            $guestPerms = $config['permissions']['guest'];
            // Guest should not be able to create, update, or delete by default
            $this->assertFalse($guestPerms['create'], "Guest should not create on $table");
            $this->assertFalse($guestPerms['update'], "Guest should not update on $table");
            $this->assertFalse($guestPerms['delete'], "Guest should not delete on $table");
        }
    }

    /**
     * Test that user has ownership restrictions
     */
    public function testUserHasOwnershipRestrictions(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';

        // Check specific tables where user should have restrictions
        foreach (['categories', 'items'] as $table) {
            if (isset($permissions[$table])) {
                $userPerms = $permissions[$table]['permissions']['user'];
                $this->assertTrue($userPerms['read_own_only'], "User should have read_own_only on $table");
                $this->assertTrue($userPerms['update_own_only'], "User should have update_own_only on $table");
                $this->assertTrue($userPerms['delete_own_only'], "User should have delete_own_only on $table");
            }
        }
    }

    /**
     * Test that authorization checks are in public/index.php
     */
    public function testAuthorizationChecksInIndex(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Should check permissions on CRUD operations
        $this->assertStringContainsString('canAccess', $indexSource);
        $this->assertStringContainsString('permissionChecker', $indexSource);
        $this->assertStringContainsString('403', $indexSource);
    }

    /**
     * Test that getFilterForReadAccess is implemented
     */
    public function testReadAccessFilteringImplemented(): void
    {
        $permissionCheckerSource = file_get_contents(__DIR__ . '/../../src/PermissionChecker.php');

        $this->assertStringContainsString('getFilterForReadAccess', $permissionCheckerSource);
        $this->assertStringContainsString('read_own_only', $permissionCheckerSource);
    }

    /**
     * Test that owner field is defined for each table
     */
    public function testOwnerFieldsDefined(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';

        foreach ($permissions as $table => $config) {
            if ($table === 'default') continue;
            $this->assertArrayHasKey('owner_field', $config);
            $this->assertNotEmpty($config['owner_field']);
        }
    }

    /**
     * Test that 403 Forbidden is used for authorization failures
     */
    public function testProperHttpStatus(): void
    {
        $indexSource = file_get_contents(__DIR__ . '/../../public/index.php');

        // Should return 403 Forbidden for authorization failures
        $this->assertStringContainsString('403', $indexSource);
        $this->assertStringContainsString('Forbidden', $indexSource);
    }

    /**
     * Test that audit logging captures authorization checks
     */
    public function testAuthorizationLogging(): void
    {
        $permissionCheckerSource = file_get_contents(__DIR__ . '/../../src/PermissionChecker.php');

        // Should log authorization checks
        $this->assertStringContainsString('logger->info', $permissionCheckerSource);
        $this->assertStringContainsString('logger->warning', $permissionCheckerSource);
        $this->assertStringContainsString('Authorization', $permissionCheckerSource);
    }
}
