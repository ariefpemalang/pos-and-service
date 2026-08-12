<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HiwaWhatsAppService;
use App\Services\SettingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationSettingsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    #[Test]
    public function super_admin_can_open_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Aplikasi')
            ->assertSee('Gateway WhatsApp')
            ->assertSee('Alamat Toko')
            ->assertSee('WhatsApp Toko')
            ->assertSee('Logo Toko')
            ->assertDontSee('Pengingat Servis Berkala');
    }

    #[Test]
    public function kasir_cannot_access_settings(): void
    {
        $kasir = User::factory()->create();
        $kasir->assignRole('Kasir');

        $this->actingAs($kasir)
            ->get(route('settings.edit'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_update_app_branding_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'app_name' => 'AR Service Jaya',
                'app_tagline' => 'Servis Terpercaya',
                'app_description' => 'Deskripsi toko service.',
            ])
            ->assertRedirect(route('settings.edit'));

        $settings = app(SettingService::class);
        $this->assertSame('AR Service Jaya', $settings->getString('app_name'));
        $this->assertSame('Servis Terpercaya', $settings->getString('app_tagline'));
        $this->assertSame('AR Service Jaya', brand_name());
    }

    #[Test]
    public function admin_can_update_company_address_whatsapp_and_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo-toko.png', 200, 200);

        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'app_name' => 'AR Service',
                'company_address' => 'Jl. Merdeka No. 10, Bandung',
                'company_whatsapp' => '081234567890',
                'photo' => $file,
            ])
            ->assertRedirect(route('settings.edit'));

        $settings = app(SettingService::class);
        $this->assertSame('Jl. Merdeka No. 10, Bandung', $settings->getString('company_address'));
        $this->assertSame('081234567890', $settings->getString('company_whatsapp'));
        $this->assertNotSame('', $settings->getString('company_logo'));
        $this->assertTrue(Storage::disk('public')->exists($settings->getString('company_logo')));
        $this->assertSame('Jl. Merdeka No. 10, Bandung', brand_address());
        $this->assertSame('081234567890', brand_whatsapp());
        $this->assertTrue(brand_has_custom_logo());
    }

    #[Test]
    public function updating_settings_without_logo_keeps_existing_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'app_name' => 'AR Service',
                'company_address' => 'Alamat Lama',
                'company_whatsapp' => '081111111111',
                'photo' => UploadedFile::fake()->image('logo1.png'),
            ])
            ->assertRedirect(route('settings.edit'));

        $logoPath = app(SettingService::class)->getString('company_logo');

        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'app_name' => 'AR Service',
                'company_address' => 'Alamat Baru',
                'company_whatsapp' => '082222222222',
            ])
            ->assertRedirect(route('settings.edit'));

        $settings = app(SettingService::class);
        $this->assertSame('Alamat Baru', $settings->getString('company_address'));
        $this->assertSame('082222222222', $settings->getString('company_whatsapp'));
        $this->assertSame($logoPath, $settings->getString('company_logo'));
    }

    #[Test]
    public function hiwa_service_sends_message_to_api(): void
    {
        Http::fake([
            'hiwa.my.id/*' => Http::response([
                'ok' => true,
                'job_id' => 'test-job-123',
                'message' => 'Pesan sudah masuk antrian pengiriman.',
                'quota' => ['messages_remaining' => 99, 'messages_limit_per_month' => 100],
            ], 200),
        ]);

        app(SettingService::class)->setMany([
            'hiwa_enabled' => true,
            'hiwa_token_device' => 'secret-token',
        ]);

        $result = app(HiwaWhatsAppService::class)->send('081234567890', 'Halo test');

        $this->assertTrue($result['ok']);
        $this->assertSame('test-job-123', $result['job_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hiwa.my.id/api/v1/messages'
                && $request['token_device'] === 'secret-token'
                && $request['to'] === '6281234567890'
                && $request['message'] === 'Halo test';
        });
    }
}
