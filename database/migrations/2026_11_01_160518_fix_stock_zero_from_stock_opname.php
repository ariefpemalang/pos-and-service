<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pindahkan stok dari stock_opname ke stock untuk barang yang stoknya masih 0
        DB::table('items')
            ->where('stock', 0)
            ->where('stock_opname', '>', 0)
            ->update([
                'stock' => DB::raw('stock_opname'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
