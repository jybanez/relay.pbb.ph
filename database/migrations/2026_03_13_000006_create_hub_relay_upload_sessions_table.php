<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_relay_upload_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('hub_relay_message_id')->constrained('hub_relay_messages')->cascadeOnDelete();
            $table->foreignUlid('hub_relay_attachment_id')->nullable()->constrained('hub_relay_attachments')->nullOnDelete();
            $table->string('direction', 50)->comment('local_outbound or hub_inbound');
            // Keep indexed hub IDs within legacy MySQL key limits on fresh installs.
            $table->string('source_hub_id', 120)->nullable();
            $table->string('target_hub_id', 120)->nullable();
            $table->string('attachment_name');
            $table->string('mime_type');
            $table->bigInteger('size_bytes');
            $table->string('checksum')->nullable();
            $table->integer('chunk_size_bytes');
            $table->integer('total_chunks')->nullable();
            $table->bigInteger('transferred_bytes')->default(0);
            $table->decimal('transfer_progress_percent', 5, 2)->default(0);
            $table->integer('current_chunk_index')->default(0);
            $table->string('transfer_status', 50)->default('initializing');
            $table->string('storage_disk')->default('local');
            $table->text('temp_path');
            $table->text('assembled_path')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['hub_relay_message_id', 'transfer_status'], 'hrus_msg_status_idx');
            $table->index(['hub_relay_attachment_id', 'transfer_status'], 'hrus_att_status_idx');
            $table->index(['source_hub_id', 'target_hub_id'], 'hrus_src_tgt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_relay_upload_sessions');
    }
};
