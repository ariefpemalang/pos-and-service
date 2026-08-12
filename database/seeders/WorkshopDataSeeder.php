<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\User;
use App\Models\WorkshopService;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;

class WorkshopDataSeeder extends Seeder
{
    public function run(): void
    {
        $userId = (int) (User::query()->value('id') ?? 1);
        $stockService = app(StockService::class);
        $transactionService = app(TransactionService::class);

        BankAccount::firstOrCreate(
            ['bank_name' => 'BRI', 'account_number' => '1234567890'],
            ['account_name' => 'AR Service', 'is_active' => true]
        );

        $customers = [
            ['name' => 'Budi Santoso', 'phone' => '081234567890', 'email' => 'budi.santoso@gmail.com', 'address' => 'Jl. Merdeka No. 12, Bandung', 'notes' => 'Langganan ganti LCD iPhone'],
            ['name' => 'Siti Aminah', 'phone' => '082198765432', 'email' => null, 'address' => 'Jl. Sudirman No. 45, Bandung', 'notes' => null],
            ['name' => 'Andi Pratama', 'phone' => '085612345678', 'email' => 'andi.pratama@yahoo.com', 'address' => 'Komplek Griya Asri Blok C7', 'notes' => 'Laptop Asus X441, sering overheat'],
            ['name' => 'Dewi Lestari', 'phone' => '081112223334', 'email' => 'dewi.lestari@gmail.com', 'address' => 'Jl. Cihampelas No. 88', 'notes' => 'Rutin cleaning laptop'],
            ['name' => 'Rizky Hidayat', 'phone' => '087711223344', 'email' => null, 'address' => 'Perumahan Melati Indah RT 03/05', 'notes' => 'Samsung A54, baterai soak'],
            ['name' => 'CV Digital Mandiri', 'phone' => '0221234567', 'email' => 'admin@digitalmandiri.co.id', 'address' => 'Jl. Industri Raya No. 100', 'notes' => 'Pelanggan korporat, service laptop kantor'],
        ];

        foreach ($customers as $row) {
            $customer = Customer::firstOrNew(['name' => $row['name']]);
            if (! $customer->exists) {
                $customer->code = Customer::generateCode();
            }
            $customer->fill($row);
            $customer->save();
        }

        $technicians = [
            ['name' => 'Ahmad Fauzi', 'phone' => '081320001001', 'email' => 'ahmad.fauzi@arservice.local', 'specialty' => 'Hardware Smartphone', 'commission_percent' => 15, 'is_active' => true, 'notes' => 'Senior teknisi HP'],
            ['name' => 'Eko Wijaya', 'phone' => '081320001002', 'email' => null, 'specialty' => 'Software & Unlock', 'commission_percent' => 12, 'is_active' => true, 'notes' => null],
            ['name' => 'Hendra Gunawan', 'phone' => '081320001003', 'email' => 'hendra.g@arservice.local', 'specialty' => 'Hardware Laptop', 'commission_percent' => 10, 'is_active' => true, 'notes' => null],
            ['name' => 'Yoga Pratama', 'phone' => '081320001004', 'email' => null, 'specialty' => 'Cleaning & Maintenance', 'commission_percent' => 10, 'is_active' => false, 'notes' => 'Nonaktif sementara'],
        ];

        foreach ($technicians as $row) {
            $technician = Technician::firstOrNew(['name' => $row['name']]);
            if (! $technician->exists) {
                $technician->code = Technician::generateCode();
            }
            $technician->fill($row);
            $technician->save();
        }

        $workshopServices = [
            ['name' => 'Ganti LCD HP', 'description' => 'Penggantian layar smartphone termasuk tes fungsi', 'price' => 150000],
            ['name' => 'Ganti Baterai HP', 'description' => 'Penggantian baterai smartphone', 'price' => 75000],
            ['name' => 'Install Ulang Windows', 'description' => 'Install ulang OS laptop + driver', 'price' => 100000],
            ['name' => 'Cleaning Laptop', 'description' => 'Pembersihan fan, heatsink, dan ganti thermal paste', 'price' => 120000],
            ['name' => 'Servis Berkala HP', 'description' => 'Cek hardware, software, dan perawatan berkala smartphone', 'price' => 50000],
        ];

        foreach ($workshopServices as $row) {
            $service = WorkshopService::firstOrNew(['name' => $row['name']]);
            if (! $service->exists) {
                $service->code = WorkshopService::generateCode();
            }
            $service->fill([...$row, 'is_active' => true]);
            $service->save();
        }

        $customer = Customer::where('name', 'Budi Santoso')->first();
        $technician = Technician::where('name', 'Ahmad Fauzi')->where('is_active', true)->first();
        $demoItem = Item::query()->where('name', 'like', '%LCD iPhone 11%')->first()
            ?? Item::query()->where('is_active', true)->orderBy('id')->first();
        $gantiLcd = WorkshopService::where('name', 'Ganti LCD HP')->first();

        if ($customer && $technician && $demoItem && $gantiLcd) {
            if (! StockMovement::query()
                ->where('item_id', $demoItem->id)
                ->where('type', 'in')
                ->where('notes', 'Stok awal demo transaksi')
                ->exists()) {
                $stockService->stockIn($demoItem->id, 5, $userId, null, 'Stok awal demo transaksi');
                $demoItem->refresh();
            }

            $hasDemoTx = \App\Models\Transaction::where('notes', 'Contoh transaksi gabungan dari seeder')->exists();

            if (! $hasDemoTx && $demoItem->stock >= 1) {
                $transactionService->create([
                    'customer_id' => $customer->id,
                    'technician_id' => $technician->id,
                    'notes' => 'Contoh transaksi gabungan dari seeder',
                    'items' => [['item_id' => $demoItem->id, 'quantity' => 1]],
                    'services' => [['workshop_service_id' => $gantiLcd->id, 'quantity' => 1]],
                ], $userId);
            }
        }

        $customer2 = Customer::where('name', 'Siti Aminah')->first();
        $installWindows = WorkshopService::where('name', 'Install Ulang Windows')->first();

        if ($customer2 && $technician && $installWindows) {
            $hasServiceTx = \App\Models\Transaction::where('notes', 'Contoh transaksi servis dari seeder')->exists();

            if (! $hasServiceTx) {
                $transactionService->create([
                    'customer_id' => $customer2->id,
                    'technician_id' => $technician->id,
                    'notes' => 'Contoh transaksi servis dari seeder',
                    'services' => [['workshop_service_id' => $installWindows->id, 'quantity' => 1]],
                ], $userId);
            }
        }

        $tempered = Item::query()->where('name', 'like', '%Tempered Glass%')->where('is_active', true)->first();
        if ($customer && $tempered) {
            if (! StockMovement::query()
                ->where('item_id', $tempered->id)
                ->where('type', 'in')
                ->where('notes', 'Stok awal demo penjualan')
                ->exists()) {
                $stockService->stockIn($tempered->id, 5, $userId, null, 'Stok awal demo penjualan');
                $tempered->refresh();
            }

            $hasSaleTx = \App\Models\Transaction::where('notes', 'Contoh penjualan sparepart dari seeder')->exists();

            if (! $hasSaleTx && $tempered->stock >= 2) {
                $transactionService->create([
                    'customer_id' => $customer->id,
                    'notes' => 'Contoh penjualan sparepart dari seeder',
                    'items' => [['item_id' => $tempered->id, 'quantity' => 2]],
                ], $userId);
            }
        }
    }
}
