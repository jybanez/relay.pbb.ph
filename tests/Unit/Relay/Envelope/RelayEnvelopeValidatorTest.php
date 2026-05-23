<?php

namespace Tests\Unit\Relay\Envelope;

use App\DTO\RelayEnvelopeDTO;
use App\Relay\Envelope\RelayEnvelopeValidator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RelayEnvelopeValidatorTest extends TestCase
{
    private RelayEnvelopeValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new RelayEnvelopeValidator();
    }

    public function test_valid_envelope_passes_validation(): void
    {
        $envelope = new RelayEnvelopeDTO(
            relay_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            origin_hq_hub_id: '2',
            source_hub_id: 'barangay-hub',
            source_system: 'sitrep.app',
            target_hq_hub_id: '10',
            target_systems: ['city-eoc.app'],
            hop_trace: [],
            message_type: 'sitrep.record',
            payload_format: 'json',
            payload_version: '1.0',
            created_at: Carbon::now(),
            occurred_at: Carbon::now(),
            priority: 'normal',
            payload: ['test' => 'data'],
        );

        $this->assertTrue($this->validator->validate($envelope));
    }

    public function test_missing_relay_id_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'relay_id' is required");

        $envelope = new RelayEnvelopeDTO(
            relay_id: '',
            origin_hq_hub_id: '2',
            source_hub_id: 'barangay-hub',
            source_system: 'sitrep.app',
            target_hq_hub_id: '10',
            target_systems: ['city-eoc.app'],
            hop_trace: [],
            message_type: 'sitrep.record',
            payload_format: 'json',
            payload_version: '1.0',
            created_at: Carbon::now(),
            occurred_at: Carbon::now(),
            priority: 'normal',
            payload: ['test' => 'data'],
        );

        $this->validator->validate($envelope);
    }

    public function test_missing_payload_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $envelope = new RelayEnvelopeDTO(
            relay_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            origin_hq_hub_id: '2',
            source_hub_id: 'barangay-hub',
            source_system: 'sitrep.app',
            target_hq_hub_id: '10',
            target_systems: ['city-eoc.app'],
            hop_trace: [],
            message_type: 'sitrep.record',
            payload_format: 'json',
            payload_version: '1.0',
            created_at: Carbon::now(),
            occurred_at: Carbon::now(),
            priority: 'normal',
            payload: [],
        );

        $this->validator->validate($envelope);
    }

    public function test_invalid_priority_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('priority must be one of');

        $envelope = new RelayEnvelopeDTO(
            relay_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            origin_hq_hub_id: '2',
            source_hub_id: 'barangay-hub',
            source_system: 'sitrep.app',
            target_hq_hub_id: '10',
            target_systems: ['city-eoc.app'],
            hop_trace: [],
            message_type: 'sitrep.record',
            payload_format: 'json',
            payload_version: '1.0',
            created_at: Carbon::now(),
            occurred_at: Carbon::now(),
            priority: 'invalid',
            payload: ['test' => 'data'],
        );

        $this->validator->validate($envelope);
    }

    public function test_future_occurred_at_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('occurred_at cannot be in the future');

        $envelope = new RelayEnvelopeDTO(
            relay_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            origin_hq_hub_id: '2',
            source_hub_id: 'barangay-hub',
            source_system: 'sitrep.app',
            target_hq_hub_id: '10',
            target_systems: ['city-eoc.app'],
            hop_trace: [],
            message_type: 'sitrep.record',
            payload_format: 'json',
            payload_version: '1.0',
            created_at: Carbon::now(),
            occurred_at: Carbon::now()->addHours(1),
            priority: 'normal',
            payload: ['test' => 'data'],
        );

        $this->validator->validate($envelope);
    }

    public function test_message_type_format_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('message_type must be in format "domain.type"');

        $envelope = new RelayEnvelopeDTO(
            relay_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            origin_hq_hub_id: '2',
            source_hub_id: 'barangay-hub',
            source_system: 'sitrep.app',
            target_hq_hub_id: '10',
            target_systems: ['city-eoc.app'],
            hop_trace: [],
            message_type: 'invalid_format',
            payload_format: 'json',
            payload_version: '1.0',
            created_at: Carbon::now(),
            occurred_at: Carbon::now(),
            priority: 'normal',
            payload: ['test' => 'data'],
        );

        $this->validator->validate($envelope);
    }

    public function test_duplicate_target_systems_fail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate target systems are not allowed');

        $envelope = new RelayEnvelopeDTO(
            relay_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            origin_hq_hub_id: '2',
            source_hub_id: 'barangay-hub',
            source_system: 'sitrep.app',
            target_hq_hub_id: '10',
            target_systems: ['city-eoc.app', 'city-eoc.app'],
            hop_trace: [],
            message_type: 'sitrep.record',
            payload_format: 'json',
            payload_version: '1.0',
            created_at: Carbon::now(),
            occurred_at: Carbon::now(),
            priority: 'normal',
            payload: ['test' => 'data'],
        );

        $this->validator->validate($envelope);
    }
}
