<?php

use App\Models\FinancialCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('cancelled_by');
            $table->foreignId('refund_entry_id')
                ->nullable()
                ->after('refunded_at')
                ->constrained('manual_cash_entries')
                ->nullOnDelete();
        });

        FinancialCategory::query()->firstOrCreate(
            [
                'name' => 'Pengembalian Uang Muka',
                'type' => 'expense',
            ],
            [
                'is_active' => true,
                'sort_order' => 5,
            ],
        );
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_entry_id');
            $table->dropColumn('refunded_at');
        });
    }
};
