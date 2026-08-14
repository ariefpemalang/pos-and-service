<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    #[Test]
    public function super_admin_can_view_dashboard_with_charts(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pemasukan Hari Ini')
            ->assertSee('Tren 7 Hari Terakhir')
            ->assertSee('chart-daily-trend');
    }

    #[Test]
    public function dashboard_finance_kpis_hidden_without_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $kasir = User::factory()->create();
        $kasir->assignRole('Kasir');

        $this->actingAs($kasir)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pemasukan Hari Ini')
            ->assertDontSee('Estimasi Sisa Kas');
    }

    #[Test]
    public function dashboard_finance_kpis_visible_with_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $kasir = User::factory()->create();
        $kasir->assignRole('Kasir');
        $kasir->givePermissionTo('dashboard finance view');

        $this->actingAs($kasir)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pemasukan Hari Ini')
            ->assertSee('Estimasi Sisa Kas');
    }

    #[Test]
    public function dashboard_low_stock_hidden_without_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $kasir = User::factory()->create();
        $kasir->assignRole('Kasir');

        $this->actingAs($kasir)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Stok Menipis');
    }

    #[Test]
    public function dashboard_low_stock_visible_with_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $kasir = User::factory()->create();
        $kasir->assignRole('Kasir');
        $kasir->givePermissionTo('dashboard low stock view');

        $this->actingAs($kasir)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Stok Menipis');
    }

    #[Test]
    public function dashboard_operational_cards_hidden_without_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $kasir = User::factory()->create();
        $kasir->assignRole('Kasir');

        $this->actingAs($kasir)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Barang Aktif')
            ->assertDontSee('stat-label">Pelanggan')
            ->assertDontSee('Komisi Bulan Ini');
    }

    #[Test]
    public function dashboard_operational_cards_visible_with_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $kasir = User::factory()->create();
        $kasir->assignRole('Kasir');
        $kasir->givePermissionTo([
            'dashboard items view',
            'dashboard customers view',
            'dashboard commission view',
        ]);

        $this->actingAs($kasir)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Barang Aktif')
            ->assertSee('Komisi Bulan Ini');
    }
}
