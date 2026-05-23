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
        Schema::create('hub_relay_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('hub_relay_message_id')->constrained('hub_relay_messages')->cascadeOnDelete();
            $table->string('attachment_type')->comment('Type: file, image, binary');
            $table->string('name')->comment('Original filename');
            $table->string('mime_type')->comment('MIME type');
            $table->bigInteger('size_bytes')->comment('File size in bytes');
            $table->string('storage_disk')->default('local')->comment('Disk where file is stored');
            $table->text('storage_path')->comment('Path in storage disk');
            $table->string('checksum')->nullable()->comment('Checksum for integrity verification');
            $table->timestamps();

            // Indexes
            $table->index('hub_relay_message_id');
            $table->index('attachment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_relay_attachments');
    }
};
