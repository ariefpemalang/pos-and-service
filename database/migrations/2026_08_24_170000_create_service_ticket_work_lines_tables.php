<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('service_ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('item_code', 50);
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index(['service_ticket_id', 'item_id']);
        });

        Schema::create('service_ticket_service_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('service_ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->foreignId('workshop_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_code', 50)->nullable();
            $table->string('service_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index('service_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ticket_service_lines');
        Schema::dropIfExists('service_ticket_items');
    }
};
