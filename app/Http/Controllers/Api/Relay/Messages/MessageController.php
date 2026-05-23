<?php

namespace App\Http\Controllers\Api\Relay\Messages;

use App\DTO\RelayEnvelopeDTO;
use App\Http\Controllers\Controller;
use App\Models\HubRelayClient;
use App\Models\HubRelayMessage;
use App\Relay\Registry\RelayNodeIdentityService;
use App\Relay\Outbound\RelaySubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function __construct(
        private RelaySubmissionService $submissionService,
        private RelayNodeIdentityService $nodeIdentity,
    ) {}

    /**
     * Submit a new message for relay
     *
     * Expected JSON:
     * {
     *   "source_system": "sitrep.app",
     *   "target_systems": ["city-eoc.app", "provincial-forwarder.app"],
     *   "message_type": "sitrep.record",
     *   "payload_format": "json",
     *   "payload_version": "1.0",
     *   "payload": { ... },
     *   "priority": "normal",
     *   "occurred_at": "2026-03-13T12:00:00Z"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $client = $this->relayClient($request);

            $validated = $request->validate([
                'source_system' => 'required|string',
                'target_systems' => 'required|array|min:1',
                'target_systems.*' => 'required|string|max:100',
                'message_type' => 'required|string',
                'payload_format' => 'nullable|string|in:json,file,image,binary',
                'payload_version' => 'nullable|string',
                'reference_type' => 'nullable|string',
                'reference_id' => 'nullable|string',
                'payload' => 'required|array',
                'tags' => 'nullable|array',
                'priority' => 'nullable|string|in:low,normal,high,urgent',
                'correlation_id' => 'nullable|string',
                'attachments_count' => 'nullable|integer',
                'occurred_at' => 'nullable|date',
            ]);

            $localHqId = $this->nodeIdentity->localHqId();
            if (! is_string($localHqId) || $localHqId === '') {
                throw new \InvalidArgumentException('Local HQ hub ID is not configured for relay submissions.');
            }

            $envelope = RelayEnvelopeDTO::fromArray(array_merge($validated, [
                'origin_hq_hub_id' => $localHqId,
                'source_hub_id' => $localHqId,
                'target_hq_hub_id' => $localHqId,
                'hop_trace' => [[
                    'hub_id' => $localHqId,
                    'event' => 'submitted',
                    'at' => now()->toIso8601String(),
                ]],
            ]));

            // Submit for relay
            $result = $this->submissionService->submit($envelope, $client);

            return response()->json([
                'success' => true,
                'relay_id' => $result['message']->relay_id,
                'message_id' => $result['message']->id,
                'status' => 'queued',
                'deliveries_count' => count($result['deliveries']),
                'deliveries' => array_map(fn($delivery) => [
                    'id' => $delivery->id,
                    'target_hq_hub_id' => $delivery->target_hq_hub_id ?: $delivery->target_hub_id,
                    'status' => $delivery->status,
                ], $result['deliveries']),
            ], 201);
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
                'error' => 'Failed to submit message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List messages
     *
     * Query parameters:
     * - status: queued, delivered, failed, etc.
     * - source_system: filter by source
     * - message_type: filter by type
     * - limit: page size
     * - page: pagination
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->relayClient($request);
        $query = HubRelayMessage::query()
            ->where('hub_relay_client_id', $client->id);

        if ($request->has('source_system')) {
            $query->where('source_system', $request->input('source_system'));
        }

        if ($request->has('message_type')) {
            $query->where('message_type', $request->input('message_type'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $messages = $query->latest('created_at')
            ->paginate($request->input('limit', 25));

        return response()->json([
            'data' => $messages->items(),
            'pagination' => [
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
            ],
        ]);
    }

    /**
     * Get a specific message with its deliveries
     */
    public function show(Request $request, HubRelayMessage $message): JsonResponse
    {
        abort_unless($message->hub_relay_client_id === $this->relayClient($request)->id, 404);

        return response()->json([
            'message' => $message,
            'deliveries' => $message->deliveries()->get(),
            'attachments' => $message->attachments()->get(),
        ]);
    }

    private function relayClient(Request $request): HubRelayClient
    {
        return $request->attributes->get('relay_client');
    }
}
