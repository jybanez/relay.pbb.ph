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
        Schema::create('hub_relay_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('relay_id')->unique()->comment('Globally unique relay ID for idempotency');
            $table->string('source_hub_id')->comment('Hub that originated the message');
            $table->string('source_system')->comment('Local application system sending message');
            $table->json('target_hub_ids')->comment('Array of target hub IDs');
            $table->string('message_type')->comment('Type of message (e.g., sitrep.record)');
            $table->string('payload_format', 50)->default('json')->comment('Format of payload (json, file, image, binary)');
            $table->string('payload_version', 50)->default('1.0')->comment('Version of payload format');
            $table->string('reference_type')->nullable()->comment('Type of referenced entity');
            $table->string('reference_id')->nullable()->comment('ID of referenced entity');
            $table->string('content_hash', 64)->nullable()->comment('SHA256 hash for deduplication');
            $table->json('payload')->comment('Actual message payload');
            $table->json('tags')->nullable()->comment('Optional tags for categorization');
            $table->string('priority', 50)->default('normal')->comment('Message priority (normal, high, urgent)');
            $table->integer('attachments_count')->default(0)->comment('Count of attached files');
            $table->string('correlation_id')->nullable()->comment('Optional correlation ID for grouping related messages');
            $table->timestamp('occurred_at')->comment('When the event actually occurred');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index('source_hub_id');
            $table->index('source_system');
            $table->index('message_type');
            $table->index('created_at');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_relay_messages');
    }
};
