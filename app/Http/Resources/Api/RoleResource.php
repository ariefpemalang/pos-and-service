<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Spatie\Permission\Models\Role */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usersCount = (int) ($this->users_count ?? 0);
        $isProtected = $this->name === 'Super Admin';
        $permissionsCount = (int) (
            $this->permissions_count
            ?? ($this->relationLoaded('permissions') ? $this->permissions->count() : 0)
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_protected' => $isProtected,
            'can_delete' => ! $isProtected && $usersCount === 0,
            'users_count' => $usersCount,
            'permissions_count' => $permissionsCount,
            'permissions' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $this->permissions->pluck('name')->values(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
