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
        Schema::create('hub_relay_clients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->comment('Client application name');
            $table->string('system_code', 100)->unique()->comment('System identifier (e.g., sitrep.app)');
            $table->string('api_key', 191)->unique()->comment('API key for authentication');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->comment('Whether client is enabled');
            $table->timestamp('last_used_at')->nullable()->comment('When client last made a request');
            $table->timestamps();

            // Indexes
            $table->index('system_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_relay_clients');
    }
};
