<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->decimal('down_payment', 15, 2)->default(0)->after('estimated_cost');
            $table->enum('down_payment_payment_method', ['cash', 'qris', 'transfer'])->nullable()->after('down_payment');
            $table->foreignId('down_payment_bank_account_id')
                ->nullable()
                ->after('down_payment_payment_method')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('down_payment', 15, 2)->default(0)->after('discount');
            $table->boolean('is_down_payment')->default(false)->after('down_payment');
            $table->index('is_down_payment');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['is_down_payment']);
            $table->dropColumn(['down_payment', 'is_down_payment']);
        });

        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('down_payment_bank_account_id');
            $table->dropColumn(['down_payment', 'down_payment_payment_method']);
        });
    }
};
