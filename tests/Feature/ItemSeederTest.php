<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use Database\Seeders\ItemSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemSeederTest extends TestCase
{
    #[Test]
    public function item_seeder_creates_smartphone_and_laptop_spareparts(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(ItemSeeder::class);

        $this->assertGreaterThanOrEqual(4, ItemCategory::count());
        $this->assertGreaterThanOrEqual(10, Item::count());

        $lcd = Item::where('name', 'like', '%LCD iPhone 11%')->first();
        $this->assertNotNull($lcd);
        $this->assertSame(2, (int) $lcd->stock_opname);
        $this->assertGreaterThan(0, (int) $lcd->stock);
        $this->assertTrue(ItemCategory::where('name', 'Sparepart HP')->exists());
        $this->assertTrue(ItemCategory::where('name', 'Sparepart Laptop')->exists());
    }
}
