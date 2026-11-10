<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions from the "Roles & permissions" admin screen.
 */
class RolesSeeder extends Seeder
{
    public const PERMISSIONS = [
        'edit content' => 'Edit questions & notes',
        'publish content' => 'Publish without review',
        'answer doubts' => 'Answer doubts as faculty',
        'approve payments' => 'Approve payment receipts',
        'manage plans' => 'Change plan prices',
        'manage users' => 'Suspend or block users',
        'send notifications' => 'Send push notifications',
        'view analytics' => 'View analytics & export',
    ];

    public const ROLES = [
        'Super admin' => ['*'],
        'Content editor' => ['edit content', 'answer doubts', 'send notifications', 'view analytics'],
        'Faculty' => ['answer doubts', 'view analytics'],
        'Finance' => ['approve payments', 'manage plans', 'view analytics'],
        'Support' => ['manage users', 'send notifications'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $p) {
            Permission::findOrCreate($p, 'web');
        }
        foreach (self::ROLES as $role => $perms) {
            $r = Role::findOrCreate($role, 'web');
            if ($perms !== ['*'] && $r->permissions()->count() === 0) {
                $r->syncPermissions($perms);
            }
        }
    }
}
