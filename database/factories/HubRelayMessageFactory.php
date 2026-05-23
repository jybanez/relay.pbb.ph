<?php

namespace Database\Factories;

use App\Models\HubRelayMessage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HubRelayMessageFactory extends Factory
{
    protected $model = HubRelayMessage::class;

    public function definition(): array
    {
        $targetSystem = $this->faker->slug() . '.' . $this->faker->slug();

        return [
            'hub_relay_client_id' => null,
            'relay_id' => (string) Str::ulid(),
            'origin_hq_hub_id' => '10',
            'source_hub_id' => $this->faker->slug(),
            'source_system' => $this->faker->slug() . '.' . $this->faker->slug(),
            'target_hub_ids' => ['10'],
            'targets' => [],
            'target_system' => $targetSystem,
            'target_systems' => [$targetSystem],
            'hop_trace' => [
                [
                    'hub_id' => '10',
                    'event' => 'submitted',
                    'at' => Carbon::now()->toIso8601String(),
                ],
            ],
            'message_type' => $this->faker->slug() . '.' . $this->faker->slug(),
            'payload_format' => 'json',
            'payload_version' => '1.0',
            'reference_type' => null,
            'reference_id' => null,
            'content_hash' => $this->faker->sha256(),
            'payload' => [
                'test' => 'data',
                'id' => $this->faker->numberBetween(1, 1000),
            ],
            'tags' => [],
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'attachments_count' => 0,
            'correlation_id' => null,
            'occurred_at' => Carbon::now(),
        ];
    }
}
