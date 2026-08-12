<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('items', 'member_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('member_price');
            });
        }

        if (Schema::hasColumn('customers', 'is_member')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('is_member');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('items', 'member_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('member_price', 15, 2)->default(0)->after('selling_price');
            });
        }

        if (! Schema::hasColumn('customers', 'is_member')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('is_member')->default(false)->after('notes');
            });
        }
    }
};
