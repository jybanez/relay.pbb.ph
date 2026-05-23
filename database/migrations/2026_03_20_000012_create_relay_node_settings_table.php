<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relay_node_settings', function (Blueprint $table) {
            $table->id();
            $table->string('local_relay_hub_id')->nullable();
            $table->unsignedBigInteger('local_hq_id')->nullable();
            $table->boolean('hq_sync_enabled')->default(false);
            $table->timestamp('hq_last_sync_at')->nullable();
            $table->string('hq_last_sync_status', 40)->nullable();
            $table->text('hq_last_sync_error')->nullable();
            $table->string('outbound_topology_mode', 40)->default('manual');
            $table->string('inbound_trust_mode', 40)->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_node_settings');
    }
};
