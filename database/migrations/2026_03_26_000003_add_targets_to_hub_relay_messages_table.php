<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_relay_messages', function (Blueprint $table) {
            $table->json('targets')->nullable()->after('target_system');
            $table->json('target_systems')->nullable()->after('targets');
        });

        DB::table('hub_relay_messages')
            ->select(['id', 'target_hub_ids', 'target_system'])
            ->orderBy('id')
            ->chunkById(100, function ($messages): void {
                foreach ($messages as $message) {
                    $targetHubIds = json_decode((string) $message->target_hub_ids, true);
                    $targetSystem = $message->target_system;

                    if (! is_array($targetHubIds) || ! is_string($targetSystem) || $targetSystem === '') {
                        continue;
                    }

                    $targets = collect($targetHubIds)
                        ->filter(fn ($targetHubId) => is_string($targetHubId) || is_int($targetHubId))
                        ->map(fn ($targetHubId) => [
                            'target_hq_hub_id' => (string) $targetHubId,
                            'target_system' => $targetSystem,
                        ])
                        ->values()
                        ->all();

                    DB::table('hub_relay_messages')
                        ->where('id', $message->id)
                        ->update([
                            'targets' => json_encode($targets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'target_systems' => json_encode([$targetSystem], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('hub_relay_messages', function (Blueprint $table) {
            $table->dropColumn(['targets', 'target_systems']);
        });
    }
};
