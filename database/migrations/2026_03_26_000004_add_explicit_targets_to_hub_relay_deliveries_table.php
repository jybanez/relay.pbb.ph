<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_relay_deliveries', function (Blueprint $table) {
            $table->string('target_hq_hub_id')->nullable()->after('target_hub_id');
            $table->string('target_system', 100)->nullable()->after('target_hq_hub_id');
            $table->index(['target_hq_hub_id', 'status']);
            $table->index('target_system');
        });

        DB::table('hub_relay_deliveries')
            ->leftJoin('hub_relay_messages', 'hub_relay_messages.id', '=', 'hub_relay_deliveries.hub_relay_message_id')
            ->select([
                'hub_relay_deliveries.id',
                'hub_relay_deliveries.target_hub_id',
                'hub_relay_messages.target_system',
            ])
            ->orderBy('hub_relay_deliveries.id')
            ->chunk(100, function ($deliveries): void {
                foreach ($deliveries as $delivery) {
                    DB::table('hub_relay_deliveries')
                        ->where('id', $delivery->id)
                        ->update([
                            'target_hq_hub_id' => $delivery->target_hub_id,
                            'target_system' => $delivery->target_system,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('hub_relay_deliveries', function (Blueprint $table) {
            $table->dropIndex(['target_hq_hub_id', 'status']);
            $table->dropIndex(['target_system']);
            $table->dropColumn(['target_hq_hub_id', 'target_system']);
        });
    }
};
