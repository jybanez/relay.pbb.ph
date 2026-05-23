<?php

namespace App\Relay\Inbound;

use App\Models\HubRelayReceipt;

/**
 * RelayIdempotencyService
 *
 * Handles idempotency checks and duplicate detection for inbound messages.
 * Uses relay_id as the primary idempotency key.
 */
class RelayIdempotencyService
{
    /**
     * Check if a relay_id has been seen before
     */
    public function isDuplicate(string $relayId): bool
    {
        return HubRelayReceipt::where('relay_id', $relayId)->exists();
    }

    /**
     * Get the previous receipt if message was duplicate
     */
    public function getPreviousReceipt(string $relayId): ?HubRelayReceipt
    {
        return HubRelayReceipt::where('relay_id', $relayId)->first();
    }

    /**
     * Mark an existing receipt as duplicate when the same relay_id is seen again.
     */
    public function markAsDuplicate(string $relayId): ?HubRelayReceipt
    {
        $receipt = $this->getPreviousReceipt($relayId);

        if ($receipt === null) {
            return null;
        }

        $receipt->update([
            'status' => HubRelayReceipt::STATUS_DUPLICATE,
            'processing_notes' => 'Duplicate receive acknowledged',
        ]);

        return $receipt->fresh();
    }

    /**
     * Mark a relay_id as received (duplicate)
     */
    public function markAsReceived(string $relayId, string $sourceHubId, string $messageType, ?string $contentHash = null): HubRelayReceipt
    {
        return HubRelayReceipt::create([
            'relay_id' => $relayId,
            'source_hub_id' => $sourceHubId,
            'message_type' => $messageType,
            'status' => HubRelayReceipt::STATUS_RECEIVED,
            'content_hash' => $contentHash,
            'received_at' => now(),
        ]);
    }

    /**
     * Mark a receipt as processed
     */
    public function markAsProcessed(HubRelayReceipt $receipt, ?string $notes = null): HubRelayReceipt
    {
        $receipt->update([
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'processed_at' => now(),
            'processing_notes' => $notes,
        ]);

        return $receipt;
    }

    /**
     * Mark a receipt as rejected
     */
    public function markAsRejected(HubRelayReceipt $receipt, string $reason = ''): HubRelayReceipt
    {
        $receipt->update([
            'status' => HubRelayReceipt::STATUS_REJECTED,
            'processing_notes' => $reason,
        ]);

        return $receipt;
    }

    public function markAsUndeliverable(HubRelayReceipt $receipt, string $reason = ''): HubRelayReceipt
    {
        $receipt->update([
            'status' => HubRelayReceipt::STATUS_UNDELIVERABLE,
            'processed_at' => now(),
            'processing_notes' => $reason,
        ]);

        return $receipt;
    }
}
