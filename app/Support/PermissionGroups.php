<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionGroups
{
    /**
     * @return list<array{group: string, access: list<string>}>
     */
    public static function all(): array
    {
        static $groups = null;

        if ($groups === null) {
            $groups = require config_path('permissions.php');
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return collect(self::all())
            ->pluck('access')
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    public static function syncToDatabase(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');

        foreach (self::names() as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }

        $superAdmin = Role::query()->where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(self::names());
        }
    }
}
