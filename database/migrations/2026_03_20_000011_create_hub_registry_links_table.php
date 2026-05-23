<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_registry_links', function (Blueprint $table) {
            $table->id();
            $table->string('hub_relay_hub_id', 120);
            $table->string('linked_relay_hub_id', 120)->nullable();
            $table->unsignedBigInteger('hub_hq_id');
            $table->unsignedBigInteger('linked_hq_id')->nullable();
            $table->string('relationship_type', 20);
            $table->string('uplink_type', 50)->nullable();
            $table->unsignedInteger('priority')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('linked_domain')->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['hub_hq_id', 'linked_hq_id', 'relationship_type'], 'hrl_unique_link');
            $table->index(['hub_hq_id', 'relationship_type'], 'hrl_hub_relationship_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_registry_links');
    }
};
