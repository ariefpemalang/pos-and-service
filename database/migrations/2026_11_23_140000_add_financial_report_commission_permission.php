<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permName = 'financial report commission view';
        $guard = config('auth.defaults.guard', 'web');

        $permission = Permission::firstOrCreate([
            'name' => $permName,
            'guard_name' => $guard,
        ]);

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permission);
        }

        $kasir = Role::where('name', 'Kasir')->first();
        if ($kasir) {
            $kasir->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'financial report commission view')->delete();
    }
};
