<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $pcs = ItemUnit::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'pcs']
        );
        $set = ItemUnit::firstOrCreate(
            ['name' => 'Set'],
            ['abbreviation' => 'set']
        );

        $categories = [
            'Sparepart HP' => 'LCD, baterai, flex, kamera, dan sparepart smartphone',
            'Sparepart Laptop' => 'Keyboard, RAM, SSD, charger, dan sparepart laptop',
            'Aksesoris' => 'Casing, tempered glass, kabel, dan aksesoris',
            'Tools' => 'Peralatan service dan bahan habis pakai',
        ];

        $categoryIds = [];
        foreach ($categories as $name => $description) {
            $categoryIds[$name] = ItemCategory::firstOrCreate(
                ['name' => $name],
                ['description' => $description]
            )->id;
        }

        $items = [
            ['name' => 'LCD iPhone 11 Original', 'category' => 'Sparepart HP', 'unit' => $pcs, 'buy' => 450000, 'sell' => 650000, 'stock' => 8, 'min' => 2],
            ['name' => 'LCD Samsung A14', 'category' => 'Sparepart HP', 'unit' => $pcs, 'buy' => 280000, 'sell' => 420000, 'stock' => 10, 'min' => 3],
            ['name' => 'Baterai iPhone 12', 'category' => 'Sparepart HP', 'unit' => $pcs, 'buy' => 180000, 'sell' => 280000, 'stock' => 12, 'min' => 3],
            ['name' => 'Baterai Samsung A54', 'category' => 'Sparepart HP', 'unit' => $pcs, 'buy' => 120000, 'sell' => 200000, 'stock' => 15, 'min' => 4],
            ['name' => 'Flex Charging Type-C Universal', 'category' => 'Sparepart HP', 'unit' => $pcs, 'buy' => 25000, 'sell' => 50000, 'stock' => 20, 'min' => 5],
            ['name' => 'Kamera Belakang Xiaomi Redmi Note 12', 'category' => 'Sparepart HP', 'unit' => $pcs, 'buy' => 150000, 'sell' => 250000, 'stock' => 6, 'min' => 2],
            ['name' => 'Keyboard Laptop Asus X441', 'category' => 'Sparepart Laptop', 'unit' => $pcs, 'buy' => 90000, 'sell' => 160000, 'stock' => 7, 'min' => 2],
            ['name' => 'RAM DDR4 8GB Laptop', 'category' => 'Sparepart Laptop', 'unit' => $pcs, 'buy' => 220000, 'sell' => 320000, 'stock' => 10, 'min' => 2],
            ['name' => 'SSD NVMe 256GB', 'category' => 'Sparepart Laptop', 'unit' => $pcs, 'buy' => 280000, 'sell' => 400000, 'stock' => 8, 'min' => 2],
            ['name' => 'Charger Laptop USB-C 65W', 'category' => 'Sparepart Laptop', 'unit' => $pcs, 'buy' => 85000, 'sell' => 150000, 'stock' => 12, 'min' => 3],
            ['name' => 'Fan Cooling Laptop Universal', 'category' => 'Sparepart Laptop', 'unit' => $pcs, 'buy' => 45000, 'sell' => 85000, 'stock' => 9, 'min' => 2],
            ['name' => 'Tempered Glass Universal', 'category' => 'Aksesoris', 'unit' => $pcs, 'buy' => 5000, 'sell' => 15000, 'stock' => 40, 'min' => 10],
            ['name' => 'Softcase HP Universal', 'category' => 'Aksesoris', 'unit' => $pcs, 'buy' => 8000, 'sell' => 25000, 'stock' => 30, 'min' => 8],
            ['name' => 'Kabel Data USB-C', 'category' => 'Aksesoris', 'unit' => $pcs, 'buy' => 12000, 'sell' => 30000, 'stock' => 25, 'min' => 5],
            ['name' => 'Thermal Paste 1g', 'category' => 'Tools', 'unit' => $pcs, 'buy' => 8000, 'sell' => 20000, 'stock' => 20, 'min' => 5],
            ['name' => 'Obeng Set Service HP', 'category' => 'Tools', 'unit' => $set, 'buy' => 35000, 'sell' => 65000, 'stock' => 6, 'min' => 1],
        ];

        $userId = (int) (User::query()->value('id') ?? 1);
        $stockService = app(StockService::class);

        foreach ($items as $row) {
            $item = Item::firstOrNew(['name' => $row['name']]);
            if (! $item->exists) {
                $item->code = Item::generateCode();
            }

            $item->fill([
                'category_id' => $categoryIds[$row['category']],
                'unit_id' => $row['unit']->id,
                'purchase_price' => $row['buy'],
                'selling_price' => $row['sell'],
                'stock_opname' => $row['min'],
                'description' => null,
                'is_active' => true,
            ]);
            $item->save();

            if ((int) $item->stock < $row['stock']) {
                $stockService->stockIn(
                    $item->id,
                    $row['stock'] - (int) $item->stock,
                    $userId,
                    null,
                    'Stok awal seeder sparepart HP & laptop'
                );
            }
        }
    }
}
