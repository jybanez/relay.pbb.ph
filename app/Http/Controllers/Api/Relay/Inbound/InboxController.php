<?php

namespace App\Http\Controllers\Api\Relay\Inbound;

use App\Http\Controllers\Controller;
use App\Models\HubRelayClient;
use App\Models\HubRelayMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $client = $this->relayClient($request);

        $query = HubRelayMessage::query()
            ->whereHas('receipt')
            ->whereJsonContains('target_systems', $client->system_code)
            ->with('receipt')
            ->latest('created_at');

        if ($request->filled('message_type')) {
            $query->where('message_type', $request->string('message_type'));
        }

        if ($request->filled('source_system')) {
            $query->where('source_system', $request->string('source_system'));
        }

        if ($request->filled('source_hub_id')) {
            $query->where('source_hub_id', $request->string('source_hub_id'));
        }

        if ($request->filled('status')) {
            $query->whereHas('receipt', fn ($receiptQuery) => $receiptQuery->where('status', $request->string('status')));
        }

        $messages = $query->paginate($request->integer('limit', 25));

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

    public function show(Request $request, HubRelayMessage $message): JsonResponse
    {
        abort_unless($message->receipt()->exists(), 404);
        abort_unless(
            in_array($this->relayClient($request)->system_code, $message->target_systems ?? [], true),
            404
        );

        return response()->json([
            'message' => $message,
            'receipt' => $message->receipt()->first(),
            'attachments' => $message->attachments()->get(),
        ]);
    }

    private function relayClient(Request $request): HubRelayClient
    {
        return $request->attributes->get('relay_client');
    }
}
