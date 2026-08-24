<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceTicketTest extends TestCase
{
    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->customer = Customer::create([
            'code' => 'PLG-TKS-01',
            'name' => 'Budi Servis',
            'phone' => '081234567890',
        ]);
    }

    #[Test]
    public function admin_can_create_ticket_and_send_whatsapp(): void
    {
        Http::fake([
            'hiwa.my.id/*' => Http::response([
                'ok' => true,
                'job_id' => 'wa-ticket-1',
                'message' => 'Pesan sudah masuk antrian pengiriman.',
            ], 200),
        ]);

        app(SettingService::class)->setMany([
            'hiwa_enabled' => true,
            'hiwa_token_device' => 'token-abc',
            'app_name' => 'AR Service',
        ]);

        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Samsung',
                'model' => 'A54',
                'serial_imei' => '123456789012345',
                'specs' => '8/256, hitam',
                'accessories' => 'Charger',
                'complaint' => 'LCD pecah',
                'notify_customer' => 1,
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('received', $ticket->status);
        $this->assertSame('Samsung', $ticket->brand);
        $this->assertSame(0, $ticket->warrantyDays());
        $this->assertTrue($ticket->statusLogs()->where('notified', true)->exists());

        $this->get(route('service-tickets.track', $ticket->tracking_token))
            ->assertOk()
            ->assertSee('LCD pecah')
            ->assertSee('Samsung A54')
            ->assertDontSee('Catatan internal');

        Http::assertSent(fn ($request) => str_contains((string) $request['message'], $ticket->trackingUrl()));
    }

    #[Test]
    public function ticket_can_be_saved_without_whatsapp_notification(): void
    {
        Http::fake([
            'hiwa.my.id/*' => Http::response(['ok' => true, 'job_id' => 'wa-skip'], 200),
        ]);

        app(SettingService::class)->setMany([
            'hiwa_enabled' => true,
            'hiwa_token_device' => 'token-abc',
        ]);

        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Vivo',
                'model' => 'Y21',
                'complaint' => 'Mati total',
                'notify_customer' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Tiket servis berhasil dibuat.')
            ->assertSessionMissing('warning');

        $ticket = ServiceTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertFalse($ticket->statusLogs()->where('notified', true)->exists());
        Http::assertNothingSent();
    }

    #[Test]
    public function status_update_notifies_customer(): void
    {
        Http::fake([
            'hiwa.my.id/*' => Http::response(['ok' => true, 'job_id' => 'wa-2'], 200),
        ]);

        app(SettingService::class)->setMany([
            'hiwa_enabled' => true,
            'hiwa_token_device' => 'token-abc',
        ]);

        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'laptop',
                'brand' => 'Asus',
                'model' => 'X441',
                'complaint' => 'Overheat',
            ]);

        $ticket = ServiceTicket::query()->first();

        $service = $this->makeWorkshopService();

        $this->actingAs($this->admin)
            ->post(route('service-tickets.status', $ticket), [
                'status' => 'ready',
                'note' => 'Siap diambil',
                'notify_customer' => 1,
                'services' => [
                    ['workshop_service_id' => $service->id, 'quantity' => 1, 'unit_price' => 150000],
                ],
            ])
            ->assertRedirect(route('service-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('ready', $ticket->status);
        $this->assertTrue($ticket->hasWorkLines());
        $this->assertSame(150000.0, (float) $ticket->estimated_cost);

        $this->get(route('service-tickets.track', $ticket->tracking_token))
            ->assertSee('Bisa diambil')
            ->assertSee('Siap diambil');
    }

    #[Test]
    public function guest_cannot_open_admin_ticket_pages(): void
    {
        $this->get(route('service-tickets.index'))->assertRedirect();
    }

    #[Test]
    public function ticket_index_shows_status_filters_and_can_filter_completed(): void
    {
        $open = $this->makeTicket();

        $done = $this->makeTicket();
        $done->update(['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($this->admin)
            ->get(route('service-tickets.index'))
            ->assertOk()
            ->assertSee('Sudah diambil')
            ->assertSee('Bisa diambil');

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('service-tickets.index', [
                'status' => 'completed',
                'draw' => 1,
                'start' => 0,
                'length' => 25,
            ]))
            ->assertOk();

        $json = $response->json();
        $this->assertSame(1, $json['recordsFiltered']);
        $this->assertSame($done->ticket_no, $json['data'][0]['ticket_no']);
        $this->assertSame(2, $json['summary']['all']);
        $this->assertSame(1, $json['summary']['open']);
        $this->assertSame(1, $json['summary']['statuses']['completed']);
        $this->assertNotSame($open->id, $done->id);
        $this->assertStringNotContainsString($open->ticket_no, json_encode($json['data']));
    }

    #[Test]
    public function ticket_can_be_created_with_inline_new_customer(): void
    {
        $before = Customer::count();

        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => '__new__',
                'new_customer_name' => 'Rina Baru',
                'new_customer_phone' => '081298765432',
                'new_customer_address' => 'Bandung',
                'device_type' => 'smartphone',
                'brand' => 'Xiaomi',
                'model' => 'Redmi Note',
                'complaint' => 'Tidak charging',
            ])
            ->assertRedirect();

        $this->assertSame($before + 1, Customer::count());
        $this->assertDatabaseHas('customers', [
            'name' => 'Rina Baru',
            'phone' => '081298765432',
        ]);

        $ticket = ServiceTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('Rina Baru', $ticket->customer?->name);
        $this->assertSame('Xiaomi', $ticket->brand);
    }

    #[Test]
    public function ticket_stores_warranty_and_activates_after_payment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Samsung',
                'model' => 'A54',
                'complaint' => 'LCD pecah',
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->firstOrFail();
        $this->assertSame(0, $ticket->warrantyDays());
        $this->assertSame('none', $ticket->warrantyStatus());

        $technician = $this->makeTechnician();
        $service = $this->makeWorkshopService();
        $this->markReadyForInvoice($ticket);

        app(\App\Services\TransactionService::class)->create([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'technician_id' => $technician->id,
            'service_ticket_id' => $ticket->id,
            'payment_method' => 'cash',
            'amount_paid' => 150000,
            'warranty_preset' => 'custom',
            'warranty_days' => 14,
            'warranty_notes' => 'Hanya kerusakan yang sama, tidak termasuk jatuh.',
            'services' => [['workshop_service_id' => $service->id, 'quantity' => 1]],
        ], $this->admin->id);

        $ticket->refresh();
        $this->assertSame('completed', $ticket->status);
        $this->assertSame('active', $ticket->warrantyStatus());
        $this->assertNotNull($ticket->warrantyEndsAt());
        $this->assertSame('Hanya kerusakan yang sama, tidak termasuk jatuh.', $ticket->warranty_notes);
        $this->assertSame(
            $ticket->completed_at->copy()->addDays(14)->toDateString(),
            $ticket->warrantyEndsAt()->toDateString(),
        );

        $this->get(route('service-tickets.track', $ticket->tracking_token))
            ->assertOk()
            ->assertSee('14 hari')
            ->assertSee('Hanya kerusakan yang sama')
            ->assertSee('Aktif');

        $this->actingAs($this->admin)
            ->post(route('service-tickets.warranty', $ticket), [
                'warranty_preset' => '30',
                'warranty_notes' => 'Garansi sparepart 30 hari.',
            ])
            ->assertRedirect(route('service-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(30, $ticket->warrantyDays());
        $this->assertSame('Garansi sparepart 30 hari.', $ticket->warranty_notes);
    }

    #[Test]
    public function ticket_down_payment_creates_transaction_and_reduces_final_nota(): void
    {
        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Samsung',
                'model' => 'A54',
                'complaint' => 'LCD pecah',
                'estimated_cost' => 200000,
                'down_payment' => 50000,
                'down_payment_payment_method' => 'cash',
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->firstOrFail();
        $this->assertSame(50000.0, $ticket->downPaymentAmount());
        $this->assertFalse($ticket->canCreateInvoice());
        $this->markReadyForInvoice($ticket);
        $this->assertTrue($ticket->fresh()->canCreateInvoice());

        $dpTx = $ticket->downPaymentTransaction;
        $this->assertNotNull($dpTx);
        $this->assertTrue($dpTx->isDownPayment());
        $this->assertSame(50000.0, (float) $dpTx->total);
        $this->assertStringStartsWith('UMK-', $dpTx->transaction_no);

        $report = app(\App\Services\FinancialReportService::class)->build(now()->startOfDay(), now()->endOfDay());
        $this->assertSame(50000.0, $report['sales']['down_payment_in']);
        $this->assertSame(50000.0, $report['sales']['revenue']);

        $this->actingAs($this->admin)
            ->get(route('service-tickets.claim-slip', $ticket))
            ->assertOk()
            ->assertSee('NOTA SERVICE')
            ->assertSee('Tunjukkan struk ini saat ambil barang')
            ->assertSee('Uang muka')
            ->assertSee('50.000')
            ->assertSee($ticket->ticket_no)
            ->assertDontSee('TANDA TERIMA DP')
            ->assertDontSee('LCD pecah')
            ->assertDontSee('Keluhan');

        $technician = $this->makeTechnician();
        $service = $this->makeWorkshopService();
        $this->markReadyForInvoice($ticket);

        $tx = app(\App\Services\TransactionService::class)->create([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'technician_id' => $technician->id,
            'service_ticket_id' => $ticket->id,
            'payment_method' => 'cash',
            'amount_paid' => 100000,
            'services' => [['workshop_service_id' => $service->id, 'quantity' => 1]],
        ], $this->admin->id);

        $this->assertSame(50000.0, $tx->downPaymentAmount());
        $this->assertSame(100000.0, (float) $tx->total);
        $this->assertSame(150000.0, $tx->jobTotal());
        $this->assertSame('completed', $ticket->fresh()->status);
        $this->assertFalse($ticket->fresh()->canCreateInvoice());
    }

    #[Test]
    public function ticket_accepts_thousand_separated_cost_and_down_payment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Samsung',
                'model' => 'A54',
                'complaint' => 'LCD pecah',
                'estimated_cost' => '1.000.000',
                'down_payment' => '200.000',
                'down_payment_payment_method' => 'cash',
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->firstOrFail();
        $this->assertSame(1000000.0, (float) $ticket->estimated_cost);
        $this->assertSame(200000.0, $ticket->downPaymentAmount());
        $this->assertSame(200000.0, (float) $ticket->downPaymentTransaction->total);
    }

    #[Test]
    public function cancelling_down_payment_clears_ticket_deposit(): void
    {
        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Oppo',
                'model' => 'A16',
                'complaint' => 'Bootloop',
                'down_payment' => 25000,
                'down_payment_payment_method' => 'cash',
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->firstOrFail();
        $dpTx = $ticket->downPaymentTransaction;
        $this->assertNotNull($dpTx);

        app(\App\Services\TransactionService::class)->cancel($dpTx, $this->admin->id);

        $ticket->refresh();
        $dpTx->refresh();
        $this->assertSame(0.0, $ticket->downPaymentAmount());
        $this->assertFalse($dpTx->isCancelled());
        $this->assertTrue($dpTx->isRefunded());
        $this->assertNotNull($dpTx->refund_entry_id);
        $this->assertSame(25000.0, (float) $dpTx->refundEntry->amount);
        $this->assertSame('expense', $dpTx->refundEntry->type);
        $this->assertFalse($ticket->canCreateInvoice());
        $this->markReadyForInvoice($ticket);
        $this->assertTrue($ticket->fresh()->canCreateInvoice());
        $this->assertNull($ticket->downPaymentTransaction);

        $report = app(\App\Services\FinancialReportService::class)->build(now()->startOfDay(), now()->endOfDay());
        $this->assertSame(25000.0, $report['sales']['down_payment_in']);
        $this->assertSame(25000.0, $report['sales']['revenue']);
        $this->assertSame(25000.0, $report['manual_expense']['amount']);
    }

    #[Test]
    public function cancelling_ticket_refunds_down_payment_as_expense(): void
    {
        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Vivo',
                'model' => 'Y21',
                'complaint' => 'Tidak charging',
                'down_payment' => 40000,
                'down_payment_payment_method' => 'cash',
            ])
            ->assertRedirect();

        $ticket = ServiceTicket::query()->firstOrFail();
        $dpTx = $ticket->downPaymentTransaction;
        $this->assertNotNull($dpTx);

        $this->actingAs($this->admin)
            ->post(route('service-tickets.status', $ticket), [
                'status' => 'cancelled',
                'note' => 'Pelanggan batal servis',
                'notify_customer' => 0,
            ])
            ->assertRedirect();

        $ticket->refresh();
        $dpTx->refresh();
        $this->assertSame('cancelled', $ticket->status);
        $this->assertSame(0.0, $ticket->downPaymentAmount());
        $this->assertTrue($dpTx->isRefunded());
        $this->assertFalse($dpTx->isCancelled());
        $this->assertSame(40000.0, (float) $dpTx->refundEntry->amount);
    }

    #[Test]
    public function billing_ticket_with_service_only_completes_ticket(): void
    {
        $ticket = $this->makeTicket();
        $technician = $this->makeTechnician();
        $service = $this->makeWorkshopService();
        $this->markReadyForInvoice($ticket);

        $tx = app(\App\Services\TransactionService::class)->create([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'technician_id' => $technician->id,
            'service_ticket_id' => $ticket->id,
            'payment_method' => 'cash',
            'amount_paid' => 150000,
            'services' => [['workshop_service_id' => $service->id, 'quantity' => 1]],
        ], $this->admin->id);

        $this->assertSame('service', $tx->type);
        $this->assertSame($ticket->id, $tx->service_ticket_id);
        $this->assertSame('completed', $ticket->fresh()->status);
        $this->assertFalse($ticket->fresh()->canCreateInvoice());
    }

    #[Test]
    public function billing_ticket_with_service_and_parts_creates_combined_nota(): void
    {
        $ticket = $this->makeTicket();
        $technician = $this->makeTechnician();
        $service = $this->makeWorkshopService();
        $item = $this->makeItem();
        $this->markReadyForInvoice($ticket);

        $tx = app(\App\Services\TransactionService::class)->create([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'technician_id' => $technician->id,
            'service_ticket_id' => $ticket->id,
            'payment_method' => 'cash',
            'amount_paid' => 200000,
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
            'services' => [['workshop_service_id' => $service->id, 'quantity' => 1]],
        ], $this->admin->id);

        $this->assertSame('combined', $tx->type);
        $this->assertSame($ticket->id, $tx->service_ticket_id);
        $this->assertSame(1, $tx->items()->count());
        $this->assertSame(1, $tx->serviceLines()->count());
        $this->assertSame('completed', $ticket->fresh()->status);
    }

    #[Test]
    public function ticket_cannot_be_billed_twice(): void
    {
        $ticket = $this->makeTicket();
        $technician = $this->makeTechnician();
        $service = $this->makeWorkshopService();
        $this->markReadyForInvoice($ticket);

        app(\App\Services\TransactionService::class)->create([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'technician_id' => $technician->id,
            'service_ticket_id' => $ticket->id,
            'payment_method' => 'cash',
            'amount_paid' => 150000,
            'services' => [['workshop_service_id' => $service->id, 'quantity' => 1]],
        ], $this->admin->id);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\TransactionService::class)->create([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'technician_id' => $technician->id,
            'service_ticket_id' => $ticket->id,
            'payment_method' => 'cash',
            'amount_paid' => 150000,
            'services' => [['workshop_service_id' => $service->id, 'quantity' => 1]],
        ], $this->admin->id);
    }

    #[Test]
    public function cancelling_ticket_nota_reopens_ticket_for_billing(): void
    {
        $ticket = $this->makeTicket();
        $technician = $this->makeTechnician();
        $service = $this->makeWorkshopService();
        $this->markReadyForInvoice($ticket);

        $tx = app(\App\Services\TransactionService::class)->create([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'technician_id' => $technician->id,
            'service_ticket_id' => $ticket->id,
            'payment_method' => 'cash',
            'amount_paid' => 150000,
            'services' => [['workshop_service_id' => $service->id, 'quantity' => 1]],
        ], $this->admin->id);

        app(\App\Services\TransactionService::class)->cancel($tx, $this->admin->id);

        $ticket->refresh();
        $this->assertSame('ready', $ticket->status);
        $this->assertTrue($ticket->canCreateInvoice());
    }

    #[Test]
    public function ready_status_requires_work_lines_and_rejects_oversold_stock(): void
    {
        $ticket = $this->makeTicket();
        $service = $this->makeWorkshopService();
        $item = $this->makeItem();

        $this->actingAs($this->admin)
            ->post(route('service-tickets.status', $ticket), [
                'status' => 'ready',
                'notify_customer' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('received', $ticket->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('service-tickets.status', $ticket), [
                'status' => 'ready',
                'notify_customer' => 0,
                'items' => [
                    ['item_id' => $item->id, 'quantity' => 99, 'unit_price' => 35000],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('received', $ticket->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('service-tickets.status', $ticket), [
                'status' => 'completed',
                'notify_customer' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post(route('service-tickets.status', $ticket), [
                'status' => 'ready',
                'notify_customer' => 0,
                'services' => [
                    ['workshop_service_id' => $service->id, 'quantity' => 1, 'unit_price' => 150000],
                ],
                'items' => [
                    ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 35000],
                ],
            ])
            ->assertRedirect(route('service-tickets.show', $ticket))
            ->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('ready', $ticket->status);
        $this->assertTrue($ticket->hasWorkLines());
        $this->assertSame(5, (int) $item->fresh()->stock);
        $this->assertSame(185000.0, (float) $ticket->estimated_cost);
    }

    private function makeTicket(): ServiceTicket
    {
        $this->actingAs($this->admin)
            ->post(route('service-tickets.store'), [
                'customer_id' => $this->customer->id,
                'device_type' => 'smartphone',
                'brand' => 'Samsung',
                'model' => 'A54',
                'complaint' => 'LCD pecah',
            ]);

        return ServiceTicket::query()->latest('id')->firstOrFail();
    }

    private function markReadyForInvoice(ServiceTicket $ticket): void
    {
        $service = \App\Models\WorkshopService::query()->where('code', 'JSV-TKS-01')->first()
            ?? $this->makeWorkshopService();

        if ($ticket->status === 'ready') {
            if (! $ticket->hasWorkLines()) {
                app(\App\Services\ServiceTicketService::class)->updateWorkLines(
                    $ticket,
                    [],
                    [['workshop_service_id' => $service->id, 'quantity' => 1, 'unit_price' => (float) $service->price]],
                );
            }
            $ticket->refresh();

            return;
        }

        app(\App\Services\ServiceTicketService::class)->updateStatus(
            $ticket,
            'ready',
            'Siap diambil',
            $this->admin->id,
            false,
            [],
            [['workshop_service_id' => $service->id, 'quantity' => 1, 'unit_price' => (float) $service->price]],
        );
        $ticket->refresh();
    }

    private function makeTechnician(): \App\Models\Technician
    {
        return \App\Models\Technician::query()->firstOrCreate(
            ['code' => 'TKN-TKS-01'],
            [
                'name' => 'Teknisi Tiket',
                'commission_percent' => 20,
                'is_active' => true,
            ],
        );
    }

    private function makeWorkshopService(): \App\Models\WorkshopService
    {
        return \App\Models\WorkshopService::query()->firstOrCreate(
            ['code' => 'JSV-TKS-01'],
            [
                'name' => 'Ganti LCD',
                'price' => 150000,
                'is_active' => true,
            ],
        );
    }

    private function makeItem(): \App\Models\Item
    {
        $category = \App\Models\ItemCategory::query()->firstOrCreate(['name' => 'Sparepart HP']);
        $unit = \App\Models\ItemUnit::query()->firstOrCreate(
            ['abbreviation' => 'pcs'],
            ['name' => 'Pcs'],
        );

        return \App\Models\Item::query()->firstOrCreate(
            ['code' => 'BRG-TKS-01'],
            [
                'name' => 'LCD Samsung A54',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'stock' => 5,
                'selling_price' => 35000,
                'is_active' => true,
            ],
        );
    }
}
