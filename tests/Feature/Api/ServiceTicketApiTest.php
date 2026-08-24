<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceTicketApiTest extends TestCase
{
    private User $admin;

    private Customer $customer;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->token = $this->admin->createToken('test')->plainTextToken;
        $this->customer = Customer::create([
            'code' => 'PLG-API-TKS',
            'name' => 'Budi API',
            'phone' => '081234567890',
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
    public function can_create_list_and_show_ticket(): void
    {
        $this->api('POST', '/api/service-tickets', [
            'customer_id' => $this->customer->id,
            'device_type' => 'smartphone',
            'brand' => 'Samsung',
            'model' => 'A54',
            'complaint' => 'LCD pecah',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket.status', 'received');

        $this->api('GET', '/api/service-tickets')
            ->assertOk()
            ->assertJsonPath('data.summary.all', 1);

        $ticket = ServiceTicket::query()->first();
        $this->api('GET', '/api/service-tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('data.ticket.ticket_no', $ticket->ticket_no);
    }

    #[Test]
    public function status_change_is_locked_and_rejects_same_status(): void
    {
        $ticket = ServiceTicket::create([
            'ticket_no' => 'TKS-TEST-0001',
            'tracking_token' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'device_type' => 'smartphone',
            'brand' => 'Xiaomi',
            'model' => '13',
            'complaint' => 'Mati total',
            'status' => 'received',
            'warranty_days' => 30,
        ]);

        $this->api('POST', '/api/service-tickets/'.$ticket->id.'/status', [
            'status' => 'ready',
            'notify_customer' => false,
        ])->assertOk()->assertJsonPath('data.ticket.status', 'ready');

        $this->api('POST', '/api/service-tickets/'.$ticket->id.'/status', [
            'status' => 'ready',
            'notify_customer' => false,
        ])->assertStatus(422);
    }

    #[Test]
    public function cannot_edit_or_delete_closed_ticket_incorrectly(): void
    {
        $ticket = ServiceTicket::create([
            'ticket_no' => 'TKS-TEST-0002',
            'tracking_token' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'device_type' => 'laptop',
            'brand' => 'Asus',
            'model' => 'Vivobook',
            'complaint' => 'Keyboard error',
            'status' => 'completed',
            'warranty_days' => 30,
            'completed_at' => now(),
        ]);

        $this->api('PUT', '/api/service-tickets/'.$ticket->id, [
            'customer_id' => $this->customer->id,
            'device_type' => 'laptop',
            'brand' => 'Asus',
            'model' => 'Vivobook',
            'complaint' => 'Keyboard error',
        ])->assertStatus(422);

        $this->api('DELETE', '/api/service-tickets/'.$ticket->id)->assertStatus(422);
    }
}
