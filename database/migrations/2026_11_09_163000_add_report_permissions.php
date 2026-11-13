<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'service report view',
            'technician report view',
            'asset report view',
        ];

        $guard = config('auth.defaults.guard', 'web');

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => $guard,
            ]);
        }

        // Berikan ke Super Admin
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Berikan ke Kasir
        $kasir = Role::where('name', 'Kasir')->first();
        if ($kasir) {
            $kasir->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        $permissions = [
            'service report view',
            'technician report view',
            'asset report view',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
