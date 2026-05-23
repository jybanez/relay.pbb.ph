<?php

namespace App\Relay\Envelope;

use App\DTO\RelayEnvelopeDTO;
use Illuminate\Support\Str;

/**
 * RelayEnvelopeValidator
 *
 * Validates relay envelopes according to the protocol spec.
 */
class RelayEnvelopeValidator
{
    /**
     * Validate a complete envelope
     *
     * @throws \InvalidArgumentException
     */
    public function validate(RelayEnvelopeDTO $envelope): bool
    {
        $this->validateRequired($envelope);
        $this->validateFormats($envelope);
        $this->validateSemantics($envelope);

        return true;
    }

    /**
     * Validate required fields
     */
    private function validateRequired(RelayEnvelopeDTO $envelope): void
    {
        $required = [
            'relay_id' => $envelope->relay_id,
            'origin_hq_hub_id' => $envelope->origin_hq_hub_id,
            'source_hub_id' => $envelope->source_hub_id,
            'source_system' => $envelope->source_system,
            'target_hq_hub_id' => $envelope->target_hq_hub_id,
            'target_systems' => $envelope->target_systems,
            'message_type' => $envelope->message_type,
            'payload' => $envelope->payload,
        ];

        foreach ($required as $field => $value) {
            if (empty($value)) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
    }

    /**
     * Validate field formats
     */
    private function validateFormats(RelayEnvelopeDTO $envelope): void
    {
        // relay_id should be a ULID
        if (!$this->isValidULID($envelope->relay_id)) {
            throw new \InvalidArgumentException('relay_id must be a valid ULID');
        }

        if ($envelope->target_hq_hub_id === '') {
            throw new \InvalidArgumentException('target_hq_hub_id is required');
        }

        if (! is_array($envelope->target_systems) || empty($envelope->target_systems)) {
            throw new \InvalidArgumentException('target_systems must be a non-empty array');
        }

        $targetSystems = [];
        foreach ($envelope->target_systems as $targetSystem) {
            if (! is_string($targetSystem) || $targetSystem === '') {
                throw new \InvalidArgumentException('each target_system must be a non-empty string');
            }

            if (in_array($targetSystem, $targetSystems, true)) {
                throw new \InvalidArgumentException('duplicate target systems are not allowed');
            }

            $targetSystems[] = $targetSystem;
        }

        // message_type should be string with format
        if (!is_string($envelope->message_type) || !str_contains($envelope->message_type, '.')) {
            throw new \InvalidArgumentException('message_type must be in format "domain.type"');
        }

        // priority must be valid
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($envelope->priority, $validPriorities)) {
            throw new \InvalidArgumentException('priority must be one of: ' . implode(', ', $validPriorities));
        }
    }

    /**
     * Validate semantic rules
     */
    private function validateSemantics(RelayEnvelopeDTO $envelope): void
    {
        // occurred_at should not be in the future
        if ($envelope->occurred_at->isFuture()) {
            throw new \InvalidArgumentException('occurred_at cannot be in the future');
        }

        // payload should be non-empty
        if (is_array($envelope->payload) && empty($envelope->payload)) {
            throw new \InvalidArgumentException('payload cannot be empty');
        }
    }

    /**
     * Check if string is valid ULID
     */
    private function isValidULID(string $value): bool
    {
        // ULID format: 26 alphanumeric characters (Crockford's base32)
        return preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $value) === 1;
    }
}
