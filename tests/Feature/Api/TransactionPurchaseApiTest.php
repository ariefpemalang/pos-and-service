<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\Supplier;
use App\Models\Technician;
use App\Models\User;
use App\Models\WorkshopService;
use Database\Seeders\RoleAndPermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionPurchaseApiTest extends TestCase
{
    private User $admin;

    private string $token;

    private Item $item;

    private Customer $customer;

    private Technician $technician;

    private WorkshopService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $category = ItemCategory::create(['name' => 'API Cat']);
        $unit = ItemUnit::create(['name' => 'Pcs', 'abbreviation' => 'pcs']);

        $this->item = Item::create([
            'code' => 'BRG-API-TX-01',
            'name' => 'API Sale Item',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'stock' => 20,
            'stock_opname' => 2,
            'purchase_price' => 10000,
            'selling_price' => 25000,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'code' => 'PLG-API-TX-01',
            'name' => 'API Customer',
        ]);

        $this->technician = Technician::create([
            'code' => 'TKN-API-TX-01',
            'name' => 'API Tech',
            'commission_percent' => 20,
            'is_active' => true,
        ]);

        $this->service = WorkshopService::create([
            'code' => 'JSV-API-TX-01',
            'name' => 'API Service',
            'price' => 100000,
            'is_active' => true,
        ]);
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
    public function can_create_list_and_cancel_sale_transaction(): void
    {
        $create = $this->api('POST', '/api/transactions', [
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'amount_paid' => 75000,
            'items' => [['item_id' => $this->item->id, 'quantity' => 3]],
        ])->assertCreated();

        $id = $create->json('data.transaction.id');
        $this->assertSame('sale', $create->json('data.transaction.type'));
        $this->assertSame(17, $this->item->fresh()->stock);

        $this->api('GET', '/api/transactions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'meta', 'summary']]);

        $this->api('DELETE', '/api/transactions/'.$id)
            ->assertOk()
            ->assertJsonPath('data.transaction.status', 'cancelled');

        $this->assertSame(20, $this->item->fresh()->stock);
    }

    #[Test]
    public function service_sale_requires_technician_and_purchase_rejects_qris(): void
    {
        $this->api('POST', '/api/transactions', [
            'customer_mode' => 'umum',
            'payment_method' => 'qris',
            'services' => [['workshop_service_id' => $this->service->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->api('POST', '/api/transactions', [
            'customer_mode' => 'umum',
            'technician_id' => $this->technician->id,
            'payment_method' => 'qris',
            'services' => [['workshop_service_id' => $this->service->id, 'quantity' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.transaction.type', 'service');

        $this->api('POST', '/api/purchases', [
            'supplier_mode' => 'none',
            'payment_method' => 'qris',
            'items' => [['item_id' => $this->item->id, 'quantity' => 2]],
        ])->assertStatus(422);

        $supplier = Supplier::create([
            'code' => 'SUP-API-TX-01',
            'name' => 'API Supplier',
        ]);

        $this->api('POST', '/api/purchases', [
            'supplier_mode' => 'existing',
            'supplier_id' => $supplier->id,
            'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => 2]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.purchase.total', 20000);

        $this->assertSame(22, $this->item->fresh()->stock);
    }
}
