<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.delete-force',
            'users.ban', 'users.adjust-balance', 'users.impersonate',

            // Projects
            'projects.view', 'projects.edit', 'projects.delete', 'projects.export',

            // Payments
            'payments.view', 'payments.verify', 'payments.refund', 'payments.manual-credit',

            // Analytics
            'analytics.view', 'analytics.export',

            // AI & System
            'ai.view', 'ai.manage',
            'system.features', 'system.settings', 'system.cache', 'system.logs',

            // Audit
            'audit.view',

            // Affiliate
            'affiliate.dashboard', 'affiliate.earnings', 'affiliate.bank',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);
        $affiliate = Role::firstOrCreate(['name' => 'affiliate', 'guard_name' => 'web']);

        $superAdmin->givePermissionTo($permissions);

        $admin->givePermissionTo(collect($permissions)->reject(fn ($perm) => $perm === 'users.delete-force')->values());

        $support->givePermissionTo([
            'users.view',
            'users.edit',
            'projects.view',
            'payments.view',
            'analytics.view',
        ]);

        $affiliate->givePermissionTo([
            'affiliate.dashboard',
            'affiliate.earnings',
            'affiliate.bank',
        ]);

        $adminUser = User::where('email', 'devcentric.studio@gmail.com')->first();
        if ($adminUser) {
            $adminUser->assignRole('super_admin');
        }
    }
}
