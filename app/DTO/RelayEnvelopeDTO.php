<?php

namespace App\DTO;

use Carbon\Carbon;

/**
 * RelayEnvelope DTO
 *
 * Represents the common envelope for all Hub Relay messages.
 * This is the contract that all hubs must implement.
 */
class RelayEnvelopeDTO
{
    public function __construct(
        public string $relay_id,
        public string $origin_hq_hub_id,
        public string $source_hub_id,
        public string $source_system,
        public string $target_hq_hub_id,
        public array $target_systems,
        public array $hop_trace,
        public string $message_type,
        public string $payload_format,
        public string $payload_version,
        public Carbon $created_at,
        public Carbon $occurred_at,
        public string $priority = 'normal',
        public string $content_hash = '',
        public int $attachments_count = 0,
        public ?string $correlation_id = null,
        public ?string $reference_type = null,
        public ?string $reference_id = null,
        public array $tags = [],
        public array $payload = [],
    ) {}

    /**
     * Create from array (e.g., from API request)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            relay_id: $data['relay_id'] ?? \Illuminate\Support\Str::ulid(),
            origin_hq_hub_id: (string) $data['origin_hq_hub_id'],
            source_hub_id: $data['source_hub_id'],
            source_system: $data['source_system'],
            target_hq_hub_id: (string) $data['target_hq_hub_id'],
            target_systems: self::normalizeTargetSystems($data),
            hop_trace: self::normalizeHopTrace($data),
            message_type: $data['message_type'],
            payload_format: $data['payload_format'] ?? 'json',
            payload_version: $data['payload_version'] ?? '1.0',
            created_at: isset($data['created_at']) ? Carbon::parse($data['created_at']) : Carbon::now(),
            occurred_at: isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : Carbon::now(),
            priority: $data['priority'] ?? 'normal',
            content_hash: $data['content_hash'] ?? '',
            attachments_count: $data['attachments_count'] ?? 0,
            correlation_id: $data['correlation_id'] ?? null,
            reference_type: $data['reference_type'] ?? null,
            reference_id: $data['reference_id'] ?? null,
            tags: $data['tags'] ?? [],
            payload: $data['payload'] ?? [],
        );
    }

    /**
     * Convert to array for storage/transmission
     */
    public function toArray(): array
    {
        return [
            'relay_id' => $this->relay_id,
            'origin_hq_hub_id' => $this->origin_hq_hub_id,
            'source_hub_id' => $this->source_hub_id,
            'source_system' => $this->source_system,
            'target_hq_hub_id' => $this->target_hq_hub_id,
            'target_systems' => $this->target_systems,
            'hop_trace' => $this->hop_trace,
            'message_type' => $this->message_type,
            'payload_format' => $this->payload_format,
            'payload_version' => $this->payload_version,
            'created_at' => $this->created_at->toIso8601String(),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'priority' => $this->priority,
            'content_hash' => $this->content_hash,
            'attachments_count' => $this->attachments_count,
            'correlation_id' => $this->correlation_id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'tags' => $this->tags,
            'payload' => $this->payload,
        ];
    }

    /**
     * Compute content hash for idempotency
     */
    public function calculateContentHash(): string
    {
        $hashContent = [
            'relay_id' => $this->relay_id,
            'source_hub_id' => $this->source_hub_id,
            'message_type' => $this->message_type,
            'payload' => $this->payload,
        ];

        return hash('sha256', json_encode($hashContent));
    }

    public function targetHqHubIds(): array
    {
        return [$this->target_hq_hub_id];
    }

    public function targetSystems(): array
    {
        return collect($this->target_systems)
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    public function visitedHubIds(): array
    {
        return collect($this->hop_trace)
            ->map(fn (array $entry) => (string) ($entry['hub_id'] ?? ''))
            ->reject(fn (string $hubId) => $hubId === '')
            ->values()
            ->all();
    }

    public function withHop(string $hubId, string $event): self
    {
        $clone = clone $this;
        $clone->hop_trace = array_merge($this->hop_trace, [[
            'hub_id' => $hubId,
            'event' => $event,
            'at' => now()->toIso8601String(),
        ]]);

        return $clone;
    }

    private static function normalizeTargetSystems(array $data): array
    {
        return collect($data['target_systems'] ?? [])
            ->filter(fn ($value) => is_string($value) || is_numeric($value))
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    private static function normalizeHopTrace(array $data): array
    {
        return collect($data['hop_trace'] ?? [])
            ->filter(fn ($entry) => is_array($entry))
            ->map(fn (array $entry) => [
                'hub_id' => (string) ($entry['hub_id'] ?? ''),
                'event' => (string) ($entry['event'] ?? 'received'),
                'at' => (string) ($entry['at'] ?? now()->toIso8601String()),
            ])
            ->filter(fn (array $entry) => $entry['hub_id'] !== '')
            ->values()
            ->all();
    }
}
