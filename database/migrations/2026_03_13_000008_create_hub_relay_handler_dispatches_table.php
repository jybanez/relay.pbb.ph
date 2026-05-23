<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_relay_handler_dispatches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('hub_relay_handler_id');
            $table->ulid('hub_relay_message_id');
            $table->ulid('hub_relay_receipt_id');
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('last_response_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('hub_relay_handler_id', 'hrhd_handler_fk')
                ->references('id')
                ->on('hub_relay_handlers')
                ->cascadeOnDelete();
            $table->foreign('hub_relay_message_id', 'hrhd_message_fk')
                ->references('id')
                ->on('hub_relay_messages')
                ->cascadeOnDelete();
            $table->foreign('hub_relay_receipt_id', 'hrhd_receipt_fk')
                ->references('id')
                ->on('hub_relay_receipts')
                ->cascadeOnDelete();

            $table->index(['hub_relay_handler_id', 'status'], 'hrhd_handler_status_idx');
            $table->index(['status', 'next_retry_at'], 'hrhd_status_retry_idx');
            $table->index(['hub_relay_message_id', 'hub_relay_receipt_id'], 'hrhd_message_receipt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_relay_handler_dispatches');
    }
};
