<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_registry_hubs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hq_id')->unique();
            $table->string('relay_hub_id')->unique();
            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->string('deployment', 50);
            $table->string('domain')->nullable()->index();
            $table->string('status', 50)->index();
            $table->string('country_code', 8)->nullable();
            $table->string('reg_code', 20)->nullable();
            $table->string('prov_code', 20)->nullable();
            $table->string('citymun_code', 20)->nullable();
            $table->string('brgy_code', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('last_response_ms')->nullable();
            $table->date('deployed_at')->nullable();
            $table->boolean('has_token')->default(false);
            $table->boolean('token_is_active')->default(false);
            $table->timestamp('token_last_used_at')->nullable();
            $table->timestamp('token_revoked_at')->nullable();
            $table->timestamp('token_issued_at')->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_registry_hubs');
    }
};
