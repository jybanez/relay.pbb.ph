<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_relay_messages', function (Blueprint $table) {
            $table->string('target_system', 100)
                ->nullable()
                ->after('target_hub_ids')
                ->comment('Destination local application system code on the receiving hub');

            $table->index('target_system');
        });
    }

    public function down(): void
    {
        Schema::table('hub_relay_messages', function (Blueprint $table) {
            $table->dropIndex(['target_system']);
            $table->dropColumn('target_system');
        });
    }
};
