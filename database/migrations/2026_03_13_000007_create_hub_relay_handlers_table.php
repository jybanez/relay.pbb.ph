<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_relay_handlers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('hub_relay_client_id');
            $table->string('name');
            $table->string('endpoint_url');
            $table->string('message_type_pattern', 120)->default('*');
            $table->string('source_system', 120)->nullable();
            $table->string('source_hub_id', 120)->nullable();
            $table->string('auth_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('hub_relay_client_id', 'hrh_client_fk')
                ->references('id')
                ->on('hub_relay_clients')
                ->cascadeOnDelete();

            $table->index(['hub_relay_client_id', 'is_active'], 'hrh_client_active_idx');
            $table->index(['message_type_pattern', 'is_active'], 'hrh_msg_active_idx');
            $table->index(['source_system', 'source_hub_id'], 'hrh_src_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_relay_handlers');
    }
};
