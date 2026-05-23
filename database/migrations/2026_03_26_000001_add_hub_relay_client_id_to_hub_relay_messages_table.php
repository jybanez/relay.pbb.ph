<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_relay_messages', function (Blueprint $table): void {
            $table->foreignUlid('hub_relay_client_id')
                ->nullable()
                ->after('id')
                ->constrained('hub_relay_clients')
                ->nullOnDelete();

            $table->index('hub_relay_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('hub_relay_messages', function (Blueprint $table): void {
            $table->dropIndex(['hub_relay_client_id']);
            $table->dropConstrainedForeignId('hub_relay_client_id');
        });
    }
};
