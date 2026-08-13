<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    private User $superAdmin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');
        $this->token = $this->superAdmin->createToken('test')->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders([
            'X-API-Key' => (string) config('api.key'),
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->json($method, $uri, $data);
    }

    #[Test]
    public function index_lists_roles_with_counts(): void
    {
        $this->api('GET', '/api/roles')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['id', 'name', 'users_count', 'permissions_count', 'is_protected', 'can_delete'],
                    ],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);
    }

    #[Test]
    public function catalog_returns_permission_groups(): void
    {
        $this->api('GET', '/api/roles/catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['groups']]);
    }

    #[Test]
    public function store_adds_view_when_write_permission_selected(): void
    {
        $this->api('POST', '/api/roles', [
            'name' => 'Kasir Cabang',
            'permissions' => ['customer create', 'customer edit'],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $role = Role::findByName('Kasir Cabang');
        $this->assertTrue($role->hasPermissionTo('customer create'));
        $this->assertTrue($role->hasPermissionTo('customer edit'));
        $this->assertTrue($role->hasPermissionTo('customer view'));
    }

    #[Test]
    public function update_rejects_stale_updated_at(): void
    {
        $role = Role::create(['name' => 'Gudang']);
        $stale = $role->updated_at?->toIso8601String();

        $role->forceFill([
            'name' => 'Gudang Utama',
            'updated_at' => now()->addSeconds(5),
        ])->save();

        $this->api('PUT', '/api/roles/'.$role->id, [
            'name' => 'Gudang Cabang',
            'permissions' => ['item view'],
            'updated_at' => $stale,
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function cannot_rename_or_delete_super_admin(): void
    {
        $role = Role::findByName('Super Admin');

        $this->api('PUT', '/api/roles/'.$role->id, [
            'name' => 'Bukan Super Admin',
            'permissions' => ['dashboard view'],
            'updated_at' => $role->updated_at?->toIso8601String(),
        ])
            ->assertStatus(422);

        $this->api('DELETE', '/api/roles/'.$role->id)
            ->assertStatus(422);
    }

    #[Test]
    public function cannot_delete_role_that_still_has_users(): void
    {
        $role = Role::create(['name' => 'Kasir Toko']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->api('DELETE', '/api/roles/'.$role->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
