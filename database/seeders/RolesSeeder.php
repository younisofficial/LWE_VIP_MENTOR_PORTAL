<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /** Fine-grained permissions (e.g. a coordinator without "payments.approve"). */
    public const PERMISSIONS = [
        'courses.manage', 'batches.manage', 'enrollments.manage', 'students.manage',
        'payments.view', 'payments.approve', 'coupons.manage', 'activity.view', 'settings.manage',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $p) {
            Permission::findOrCreate($p, 'web');
        }

        Role::findOrCreate(User::ROLE_SUPER_ADMIN, 'web')->syncPermissions(self::PERMISSIONS);
        Role::findOrCreate(User::ROLE_ADMIN, 'web')->syncPermissions(array_diff(self::PERMISSIONS, ['settings.manage']));
        Role::findOrCreate(User::ROLE_MENTOR, 'web');
        Role::findOrCreate(User::ROLE_STUDENT, 'web');
        Role::findOrCreate(User::ROLE_FRANCHISER, 'web');
    }
}
