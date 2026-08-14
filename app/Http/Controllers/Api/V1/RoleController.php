<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\RoleResource;
use App\Support\PermissionGroups;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:role view')->only(['index', 'show', 'catalog']);
        $this->middleware('permission:role create')->only('store');
        $this->middleware('permission:role edit')->only('update');
        $this->middleware('permission:role delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $pivot = $this->rolePivotTable();

        $paginator = $this->paginateQuery(
            $request,
            Role::query()
                ->select('roles.*')
                ->selectSub(
                    DB::table($pivot)->selectRaw('count(*)')->whereColumn($pivot.'.role_id', 'roles.id'),
                    'users_count',
                )
                ->withCount('permissions'),
            ['name'],
            [],
        );

        return $this->apiSuccess('Daftar role berhasil diambil.', [
            'items' => RoleResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function catalog(): JsonResponse
    {
        $this->syncConfigPermissions();

        return $this->apiSuccess('Katalog permission berhasil diambil.', [
            'groups' => collect(PermissionGroups::all())
                ->map(fn (array $group) => [
                    'group' => $group['group'],
                    'access' => $group['access'],
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->syncConfigPermissions();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $permissions = $this->normalizePermissions($validated['permissions'] ?? []);

        try {
            $role = DB::transaction(function () use ($validated, $permissions) {
                $role = Role::create([
                    'name' => $validated['name'],
                    'guard_name' => (string) config('auth.defaults.guard', 'web'),
                ]);
                $role->syncPermissions($permissions);

                return $this->withCounts($role);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return $this->apiError('Nama role sudah digunakan.', 422);
            }

            throw $e;
        }

        return $this->apiSuccess('Role berhasil ditambahkan.', [
            'role' => new RoleResource($role),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return $this->apiSuccess('Detail role berhasil diambil.', [
            'role' => new RoleResource($this->withCounts($role)),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->syncConfigPermissions();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'updated_at' => ['nullable', 'date'],
        ]);

        $permissions = $this->normalizePermissions($validated['permissions'] ?? []);

        try {
            $outcome = DB::transaction(function () use ($role, $validated, $permissions) {
                /** @var Role $locked */
                $locked = Role::query()->whereKey($role->id)->lockForUpdate()->firstOrFail();

                if (! empty($validated['updated_at']) && $locked->updated_at) {
                    $tz = (string) config('app.timezone', 'UTC');
                    $incoming = Carbon::parse($validated['updated_at'])->timezone($tz)->format('Y-m-d H:i:s');
                    $current = $locked->updated_at->copy()->timezone($tz)->format('Y-m-d H:i:s');
                    if ($incoming !== $current) {
                        return [
                            'error' => true,
                            'status' => 409,
                            'message' => 'Role baru saja diubah orang lain. Muat ulang lalu simpan lagi.',
                        ];
                    }
                }

                if ($locked->name === 'Super Admin' && $validated['name'] !== 'Super Admin') {
                    return [
                        'error' => true,
                        'status' => 422,
                        'message' => 'Nama role Super Admin tidak dapat diubah.',
                    ];
                }

                $locked->update(['name' => $validated['name']]);
                $locked->syncPermissions($permissions);
                $locked->touch();

                return [
                    'error' => false,
                    'role' => $this->withCounts($locked),
                ];
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return $this->apiError('Nama role sudah digunakan.', 422);
            }

            throw $e;
        }

        if ($outcome['error'] ?? false) {
            return $this->apiError((string) $outcome['message'], (int) $outcome['status']);
        }

        return $this->apiSuccess('Role berhasil diperbarui.', [
            'role' => new RoleResource($outcome['role']),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $outcome = DB::transaction(function () use ($role) {
            /** @var Role $locked */
            $locked = Role::query()->whereKey($role->id)->lockForUpdate()->firstOrFail();

            if ($locked->name === 'Super Admin') {
                return [
                    'error' => true,
                    'status' => 422,
                    'message' => 'Role Super Admin tidak dapat dihapus.',
                ];
            }

            $usersCount = DB::table($this->rolePivotTable())
                ->where('role_id', $locked->id)
                ->lockForUpdate()
                ->count();

            if ($usersCount > 0) {
                return [
                    'error' => true,
                    'status' => 422,
                    'message' => 'Role tidak dapat dihapus karena masih digunakan user.',
                ];
            }

            $locked->syncPermissions([]);
            $locked->delete();

            return ['error' => false];
        });

        if ($outcome['error'] ?? false) {
            return $this->apiError((string) $outcome['message'], (int) $outcome['status']);
        }

        return $this->apiSuccess('Role berhasil dihapus.');
    }

    private function rolePivotTable(): string
    {
        return (string) config('permission.table_names.model_has_roles', 'model_has_roles');
    }

    private function withCounts(Role $role): Role
    {
        $role->loadMissing('permissions');
        $role->setAttribute(
            'users_count',
            (int) DB::table($this->rolePivotTable())->where('role_id', $role->id)->count(),
        );
        $role->setAttribute('permissions_count', $role->permissions->count());

        return $role;
    }

    private function syncConfigPermissions(): void
    {
        PermissionGroups::syncToDatabase();
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function normalizePermissions(array $names): array
    {
        $allowed = array_flip(PermissionGroups::names());
        $selected = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '' || ! isset($allowed[$name])) {
                continue;
            }

            $selected[$name] = true;

            if (preg_match('/^(.+) (create|edit|delete|cancel)$/', $name, $match) === 1) {
                $view = $match[1].' view';
                if (isset($allowed[$view])) {
                    $selected[$view] = true;
                }
            }
        }

        return array_keys($selected);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return $sqlState === '23000'
            || $driverCode === '1062'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
