<?php

namespace App\Http\Controllers\Api\Relay\Inbound;

use App\DTO\RelayEnvelopeDTO;
use App\Http\Controllers\Controller;
use App\Relay\Inbound\RelayReceiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReceiveController extends Controller
{
    public function __construct(
        private RelayReceiveService $receiveService,
    ) {}

    /**
     * Receive a single message from a remote hub
     *
     * Expected JSON:
     * {
     *   "relay_id": "ulid",
     *   "origin_hq_hub_id": "2",
     *   "source_hub_id": "hub-id",
     *   "source_system": "sitrep.app",
     *   "target_hq_hub_id": "10",
     *   "targets": [
     *     {"id": "15", "systems": ["city-eoc.app"]},
     *     {"id": "20", "systems": ["province-eoc.app"]}
     *   ],
     *   "hop_trace": [],
     *   "message_type": "sitrep.record",
     *   "payload_format": "json",
     *   "payload_version": "1.0",
     *   "payload": { ... },
     *   "occurred_at": "2026-03-13T12:00:00Z"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'relay_id' => 'required|string',
                'origin_hq_hub_id' => 'required',
                'source_hub_id' => 'required|string',
                'source_system' => 'required|string',
                'target_hq_hub_id' => 'required',
                'targets' => 'required|array|min:1',
                'targets.*.id' => 'required',
                'targets.*.systems' => 'required|array|min:1',
                'targets.*.systems.*' => 'required|string|max:100',
                'hop_trace' => 'nullable|array',
                'message_type' => 'required|string',
                'payload_format' => 'nullable|string',
                'payload_version' => 'nullable|string',
                'payload' => 'required|array',
                'content_hash' => 'nullable|string',
                'attachments_count' => 'nullable|integer',
                'occurred_at' => 'nullable|date',
                'created_at' => 'nullable|date',
                'correlation_id' => 'nullable|string',
                'tags' => 'nullable|array',
                'priority' => 'nullable|string',
            ]);

            // Create envelope from request
            $envelope = RelayEnvelopeDTO::fromArray($validated);

            // Process the inbound message
            $result = $this->receiveService->receive($envelope);

            $statusCode = match ($result['status']) {
                'duplicate' => 200,
                \App\Models\HubRelayReceipt::STATUS_REJECTED => 409,
                default => 201,
            };

            return response()->json([
                'success' => $result['success'],
                'status' => $result['status'],
                'relay_id' => $result['relay_id'],
                'message' => $result['success']
                    ? 'Message received and acknowledged'
                    : 'Message rejected',
            ], $statusCode);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to receive message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch receive multiple messages
     *
     * Useful for catching up after outages or initial synchronization
     *
     * Expected JSON:
     * {
     *   "messages": [
     *     { ... message envelope ... },
     *     { ... another message ... }
     *   ]
     * }
     */
    public function storeBatch(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'messages' => 'required|array|min:1',
                'messages.*.relay_id' => 'required|string',
                'messages.*.origin_hq_hub_id' => 'required',
                'messages.*.source_hub_id' => 'required|string',
                'messages.*.source_system' => 'required|string',
                'messages.*.target_hq_hub_id' => 'required',
                'messages.*.targets' => 'required|array|min:1',
                'messages.*.targets.*.id' => 'required',
                'messages.*.targets.*.systems' => 'required|array|min:1',
                'messages.*.targets.*.systems.*' => 'required|string|max:100',
                'messages.*.hop_trace' => 'nullable|array',
                'messages.*.message_type' => 'required|string',
                'messages.*.payload' => 'required|array',
                'messages.*.occurred_at' => 'nullable|date',
                'messages.*.created_at' => 'nullable|date',
            ]);

            $results = [];
            foreach ($validated['messages'] as $messageData) {
                try {
                    $envelope = RelayEnvelopeDTO::fromArray($messageData);
                    $result = $this->receiveService->receive($envelope);

                    $results[] = [
                        'relay_id' => $result['relay_id'],
                        'success' => $result['success'],
                        'status' => $result['status'],
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'relay_id' => $messageData['relay_id'] ?? null,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'results' => $results,
                'received_count' => count(array_filter($results, fn($r) => $r['success'])),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Batch receive failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
