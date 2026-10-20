<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** N2N (MASTER-ONLY): the staff scope that lets a delegated staff member work the Naara chat report queue. Admins hold every scope. */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::findOrCreate('n2n.moderate', 'web');
        if ($admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()) {
            $admin->givePermissionTo($perm);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'n2n.moderate')->delete();
    }
};
