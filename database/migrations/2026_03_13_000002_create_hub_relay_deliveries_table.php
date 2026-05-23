<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hub_relay_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('hub_relay_message_id')->constrained('hub_relay_messages')->cascadeOnDelete();
            $table->string('target_hub_id')->comment('Target hub for this delivery');
            $table->string('status', 50)->default('queued')->comment('Status: queued, sending, delivered, failed, dead');
            $table->integer('attempt_count')->default(0)->comment('Number of delivery attempts');
            $table->timestamp('last_attempt_at')->nullable()->comment('When last attempt was made');
            $table->timestamp('delivered_at')->nullable()->comment('When successfully delivered');
            $table->text('last_error')->nullable()->comment('Last error message');
            $table->timestamp('next_retry_at')->nullable()->comment('When to retry next');
            $table->timestamps();

            // Indexes for efficient querying and workers
            $table->index(['target_hub_id', 'status']);
            $table->index(['status', 'next_retry_at']);
            $table->index('hub_relay_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_relay_deliveries');
    }
};
