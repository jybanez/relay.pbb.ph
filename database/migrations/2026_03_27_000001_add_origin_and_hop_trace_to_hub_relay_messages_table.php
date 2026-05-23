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
            $table->string('origin_hq_hub_id')->nullable()->after('relay_id');
            $table->json('hop_trace')->nullable()->after('target_systems');
            $table->index('origin_hq_hub_id');
        });

        DB::table('hub_relay_messages')
            ->select(['id', 'source_hub_id', 'created_at'])
            ->orderBy('id')
            ->chunk(100, function ($messages): void {
                foreach ($messages as $message) {
                    DB::table('hub_relay_messages')
                        ->where('id', $message->id)
                        ->update([
                            'origin_hq_hub_id' => $message->source_hub_id,
                            'hop_trace' => json_encode([[
                                'hub_id' => (string) $message->source_hub_id,
                                'event' => 'submitted',
                                'at' => optional($message->created_at)?->format(DATE_ATOM) ?? now()->toIso8601String(),
                            ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('hub_relay_messages', function (Blueprint $table) {
            $table->dropIndex(['origin_hq_hub_id']);
            $table->dropColumn(['origin_hq_hub_id', 'hop_trace']);
        });
    }
};
