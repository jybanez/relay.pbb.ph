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
        Schema::create('hub_relay_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('relay_id')->unique()->comment('Same relay_id from inbound message - ensures idempotency');
            $table->string('source_hub_id')->comment('Hub that sent the message');
            $table->string('message_type')->comment('Type of message received');
            $table->string('status', 50)->default('received')->comment('Status: received, processed, duplicate, rejected');
            $table->string('content_hash', 64)->nullable()->comment('Hash for additional validation');
            $table->timestamp('received_at')->comment('When we received this message');
            $table->timestamp('processed_at')->nullable()->comment('When we processed/handed off to app');
            $table->text('processing_notes')->nullable()->comment('Any notes about processing');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index('source_hub_id');
            $table->index('message_type');
            $table->index('status');
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_relay_receipts');
    }
};
