<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\Technician;
use App\Models\User;
use App\Models\WorkshopService;
use Database\Seeders\RoleAndPermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EntityCodeApiTest extends TestCase
{
    private User $admin;

    private string $token;

    private ItemCategory $category;

    private ItemUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->category = ItemCategory::create(['name' => 'Code Cat']);
        $this->unit = ItemUnit::create(['name' => 'Pcs', 'abbreviation' => 'pcs']);
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
    public function can_create_item_with_custom_code_and_reject_duplicate(): void
    {
        $this->api('POST', '/api/items', [
            'name' => 'Kabel Data',
            'code' => 'KD',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.item.code', 'KD');

        $this->api('POST', '/api/items', [
            'name' => 'Kabel Data Duplikat',
            'code' => 'KD',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function can_update_item_code_and_reject_duplicate(): void
    {
        $first = Item::create([
            'code' => 'BRG-CODE-01',
            'name' => 'Item A',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'stock' => 0,
            'is_active' => true,
        ]);

        $second = Item::create([
            'code' => 'BRG-CODE-02',
            'name' => 'Item B',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'stock' => 0,
            'is_active' => true,
        ]);

        $this->api('PUT', '/api/items/'.$second->id, [
            'name' => 'Item B',
            'code' => 'KD-NEW',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.item.code', 'KD-NEW');

        $this->api('PUT', '/api/items/'.$second->id, [
            'name' => 'Item B',
            'code' => 'BRG-CODE-01',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertSame('BRG-CODE-01', $first->fresh()->code);
    }

    #[Test]
    public function can_create_technician_and_workshop_service_with_custom_code(): void
    {
        $this->api('POST', '/api/technicians', [
            'name' => 'Teknisi A',
            'code' => 'TEK-A',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.technician.code', 'TEK-A');

        $this->api('POST', '/api/workshop-services', [
            'name' => 'Ganti LCD',
            'code' => 'LCD',
            'price' => 250000,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.workshop_service.code', 'LCD');
    }

    #[Test]
    public function suggest_code_endpoint_returns_generated_code(): void
    {
        $this->api('GET', '/api/entity-codes/suggest', ['type' => 'item'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['code']]);

        $code = (string) $this->api('GET', '/api/entity-codes/suggest', ['type' => 'technician'])
            ->json('data.code');

        $this->assertStringStartsWith('TKN-', $code);
    }
}
