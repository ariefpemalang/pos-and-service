<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_no', 30)->unique();
            $table->uuid('tracking_token')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('device_type', ['smartphone', 'laptop']);
            $table->string('brand');
            $table->string('model');
            $table->string('color')->nullable();
            $table->string('serial_imei')->nullable();
            $table->text('specs')->nullable();
            $table->text('accessories')->nullable();
            $table->text('complaint');
            $table->text('diagnosis')->nullable();
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->enum('status', [
                'received',
                'diagnosing',
                'waiting_parts',
                'in_progress',
                'ready',
                'completed',
                'cancelled',
            ])->default('received');
            $table->text('internal_notes')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['customer_id', 'status']);
        });

        Schema::create('service_ticket_status_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('notified')->default(false);
            $table->string('hiwa_job_id')->nullable();
            $table->text('notify_error')->nullable();
            $table->timestamps();

            $table->index(['service_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ticket_status_logs');
        Schema::dropIfExists('service_tickets');
    }
};
