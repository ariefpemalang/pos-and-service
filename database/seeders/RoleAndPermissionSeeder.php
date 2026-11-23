<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionGroups;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        return [
            'dashboard view', 'dashboard finance view', 'dashboard low stock view',
            'dashboard items view', 'dashboard customers view',
            'dashboard technicians view', 'dashboard services view', 'dashboard commission view',
            'user view', 'user create', 'user edit', 'user delete',
            'role view', 'role create', 'role edit', 'role delete',
            'customer view', 'customer create', 'customer edit', 'customer delete',
            'supplier view', 'supplier create', 'supplier edit', 'supplier delete',
            'technician view', 'technician create', 'technician edit', 'technician delete',
            'item category view', 'item category create', 'item category edit', 'item category delete',
            'item unit view', 'item unit create', 'item unit edit', 'item unit delete',
            'item view', 'item create', 'item edit', 'item delete',
            'item import', 'item export',
            'stock in view', 'stock in create',
            'stock out view', 'stock out create',
            'stock report view',
            'stock opname view', 'stock opname create', 'stock opname adjust',
            'workshop service view', 'workshop service create', 'workshop service edit', 'workshop service delete',
            'service ticket view', 'service ticket create', 'service ticket edit', 'service ticket delete',
            'transaction view', 'transaction create', 'transaction edit', 'transaction delete',
            'purchase view', 'purchase create', 'purchase edit', 'purchase delete',
            'financial report view',
            'service report view',
            'technician report view',
            'asset report view',
            'manual income view', 'manual income create', 'manual income cancel',
            'manual expense view', 'manual expense create', 'manual expense cancel',
            'bank account view', 'bank account create', 'bank account edit', 'bank account delete',
            'settings view', 'settings edit',
        ];
    }

    public function syncPermissionsAndRoles(): void
    {
        foreach (array_unique([...self::permissionNames(), ...PermissionGroups::names()]) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());

        $kasir = Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web']);
        $kasir->syncPermissions([
            'dashboard view',
            'customer view', 'customer create', 'customer edit',
            'supplier view', 'supplier create', 'supplier edit',
            'technician view',
            'item view', 'item create', 'item edit', 'item category view', 'item category create', 'item unit view', 'item unit create',
            'item export',
            'stock in view', 'stock in create',
            'stock out view', 'stock out create',
            'stock report view',
            'stock opname view', 'stock opname create', 'stock opname adjust',
            'service ticket view', 'service ticket create', 'service ticket edit',
            'transaction view', 'transaction create',
            'purchase view', 'purchase create',
            'financial report view',
            'service report view',
            'technician report view',
            'asset report view',
            'manual income view', 'manual income create', 'manual income cancel',
            'manual expense view', 'manual expense create', 'manual expense cancel',
        ]);

        Role::firstOrCreate(['name' => 'Teknisi', 'guard_name' => 'web'])->syncPermissions([
            'dashboard view',
            'customer view',
            'technician view',
            'item view', 'item create', 'item edit', 'item category view', 'item category create', 'item unit view', 'item unit create',
            'stock report view',
            'service ticket view', 'service ticket edit',
            'transaction view',
        ]);
    }

    public function run(): void
    {
        $this->syncPermissionsAndRoles();

        Role::where('name', 'Admin')->each(function (Role $role) {
            $kasir = Role::firstOrCreate(['name' => 'Kasir']);
            foreach ($role->users as $user) {
                $user->syncRoles([$kasir]);
            }
            $role->delete();
        });

        $user = User::updateOrCreate(
            ['email' => 'saepulramdan244@gmail.com'],
            [
                'name' => 'Saepul Ramdan',
                'password' => Hash::make('password'),
            ]
        );

        if (! $user->hasRole('Super Admin')) {
            $user->syncRoles(['Super Admin']);
        }
    }
}
