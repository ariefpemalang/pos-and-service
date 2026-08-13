<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ManagedUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:user view')->only(['index', 'show', 'roles']);
        $this->middleware('permission:user create')->only('store');
        $this->middleware('permission:user edit')->only('update');
        $this->middleware('permission:user delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            $request,
            User::query()->with('roles'),
            ['name', 'email'],
            ['id', 'name', 'email', 'created_at', 'updated_at'],
        );

        return $this->apiSuccess('Daftar user berhasil diambil.', [
            'items' => ManagedUserResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->values();

        return $this->apiSuccess('Daftar role berhasil diambil.', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles([(int) $validated['role_id']]);
        $user->load('roles');

        return $this->apiSuccess('User berhasil ditambahkan.', [
            'user' => new ManagedUserResource($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles');

        return $this->apiSuccess('Detail user berhasil diambil.', [
            'user' => new ManagedUserResource($user),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            ...(! empty($validated['password']) ? ['password' => Hash::make($validated['password'])] : []),
        ]);

        $user->syncRoles([(int) $validated['role_id']]);
        $user->load('roles');

        return $this->apiSuccess('User berhasil diperbarui.', [
            'user' => new ManagedUserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()?->id) {
            return $this->apiError('Tidak dapat menghapus akun yang sedang login.', 422);
        }

        if ($user->id === 1) {
            return $this->apiError('User utama tidak dapat dihapus.', 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->apiSuccess('User berhasil dihapus.');
    }
}
