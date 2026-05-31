<?php

namespace App\Http\Controllers;

use App\Models\HubRelayDelivery;
use App\Models\HubRelayClient;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use App\Models\HubRelayUploadSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RelayAdminSectionController extends Controller
{
    private const SECTIONS = [
        'outbox' => 'Outbox',
        'inbox' => 'Inbox',
        'deliveries' => 'Deliveries',
        'uploads' => 'Uploads',
        'dead-letters' => 'Dead Letters',
        'clients' => 'Clients',
        'users' => 'Users',
    ];

    public function __invoke(Request $request, string $section): View
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        [, , $description] = $this->sectionPayload($section);

        return view('relay.admin-section', [
            'appName' => config('app.name'),
            'sectionKey' => $section,
            'sectionTitle' => self::SECTIONS[$section],
            'sectionDescription' => $description,
            'dataUrl' => '/relay/data/sections/'.$section,
            'sections' => self::SECTIONS,
        ]);
    }

    public function data(string $section): JsonResponse
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);

        [$rows, $columns, $description] = $this->sectionPayload($section);

        return response()->json([
            'sectionKey' => $section,
            'sectionTitle' => self::SECTIONS[$section],
            'sectionDescription' => $description,
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }

    private function sectionPayload(string $section): array
    {
        return match ($section) {
            'outbox' => $this->outboxData(),
            'inbox' => $this->inboxData(),
            'deliveries' => $this->deliveriesData(),
            'uploads' => $this->uploadsData(),
            'dead-letters' => $this->deadLettersData(),
            'clients' => $this->clientsData(),
            'users' => $this->usersData(),
        };
    }

    private function outboxData(): array
    {
        $rows = HubRelayMessage::query()
            ->with('deliveries')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(function (HubRelayMessage $message): array {
                $statuses = $message->deliveries->pluck('status')->unique()->implode(', ');
                $attempts = (int) $message->deliveries->sum('attempt_count');

                return [
                    'id' => $message->id,
                    'relay_id' => $message->relay_id,
                    'message_type' => $message->message_type,
                    'source_system' => $message->source_system,
                    'next_hops' => collect($message->targetHubIds())
                        ->filter(fn (mixed $hubId): bool => filled($hubId))
                        ->implode(', '),
                    'target_systems' => collect($message->allTargetSystems())
                        ->filter(fn (mixed $system): bool => is_string($system) && trim($system) !== '')
                        ->implode(', '),
                    'delivery_statuses' => $statuses !== '' ? $statuses : 'No deliveries',
                    'attempts' => $attempts,
                    'created_at' => $message->created_at?->diffForHumans(),
                ];
            })
            ->values();

        return [$rows, [
            ['key' => 'relay_id', 'label' => 'Relay ID'],
            ['key' => 'message_type', 'label' => 'Message Type'],
            ['key' => 'source_system', 'label' => 'Source System'],
            ['key' => 'next_hops', 'label' => 'Next Hops'],
            ['key' => 'target_systems', 'label' => 'Target Systems'],
            ['key' => 'delivery_statuses', 'label' => 'Status'],
            ['key' => 'attempts', 'label' => 'Attempts'],
            ['key' => 'created_at', 'label' => 'Created'],
        ], 'Messages created locally and routed hop-by-hop toward adjacent relays while preserving the client-owned target system audience.'];
    }

    private function inboxData(): array
    {
        $rows = HubRelayMessage::query()
            ->with('receipt')
            ->whereHas('receipt')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (HubRelayMessage $message): array => [
                'id' => $message->id,
                'relay_id' => $message->relay_id,
                'source_hub_id' => $message->source_hub_id,
                'message_type' => $message->message_type,
                'receipt_status' => $message->receipt?->status ?? 'unknown',
                'received_at' => $message->receipt?->received_at?->diffForHumans(),
                'processed_at' => $message->receipt?->processed_at?->diffForHumans() ?? 'Not processed',
            ])
            ->values();

        return [$rows, [
            ['key' => 'relay_id', 'label' => 'Relay ID'],
            ['key' => 'source_hub_id', 'label' => 'Source Hub'],
            ['key' => 'message_type', 'label' => 'Message Type'],
            ['key' => 'receipt_status', 'label' => 'Receipt Status'],
            ['key' => 'received_at', 'label' => 'Received'],
            ['key' => 'processed_at', 'label' => 'Processed'],
        ], 'Messages received from downstream or peer hubs and exposed locally.'];
    }

    private function deliveriesData(): array
    {
        $rows = HubRelayDelivery::query()
            ->with('message:id,relay_id,source_hub_id,message_type')
            ->latest('updated_at')
            ->limit(150)
            ->get()
            ->map(fn (HubRelayDelivery $delivery): array => [
                'id' => $delivery->id,
                'relay_id' => $delivery->message?->relay_id ?? $delivery->hub_relay_message_id,
                'source_hub_id' => $delivery->message?->source_hub_id ?? 'unknown',
                'target_hq_hub_id' => $delivery->target_hq_hub_id ?: $delivery->target_hub_id,
                'target_systems' => collect($delivery->message?->allTargetSystems() ?? [])
                    ->filter(fn (mixed $system): bool => is_string($system) && trim($system) !== '')
                    ->implode(', '),
                'message_type' => $delivery->message?->message_type ?? 'unknown',
                'status' => $delivery->status,
                'attempt_count' => (int) $delivery->attempt_count,
                'next_retry_at' => $delivery->next_retry_at?->diffForHumans() ?? 'Not scheduled',
                'last_error' => $delivery->last_error ?: 'None',
                'updated_at' => $delivery->updated_at?->diffForHumans(),
            ])
            ->values();

        return [$rows, [
            ['key' => 'relay_id', 'label' => 'Relay ID'],
            ['key' => 'source_hub_id', 'label' => 'Source Hub'],
            ['key' => 'target_hq_hub_id', 'label' => 'Target HQ Hub'],
            ['key' => 'target_systems', 'label' => 'Target Systems'],
            ['key' => 'message_type', 'label' => 'Message Type'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'attempt_count', 'label' => 'Attempts'],
            ['key' => 'next_retry_at', 'label' => 'Next Retry'],
            ['key' => 'last_error', 'label' => 'Last Error'],
        ], 'One row per next-hop relay delivery attempt.'];
    }

    private function uploadsData(): array
    {
        $rows = HubRelayUploadSession::query()
            ->latest('updated_at')
            ->limit(150)
            ->get()
            ->map(fn (HubRelayUploadSession $upload): array => [
                'id' => $upload->id,
                'session_id' => $upload->id,
                'attachment_name' => $upload->attachment_name,
                'relay_id' => $upload->message?->relay_id ?? $upload->hub_relay_message_id,
                'target_hub_id' => $upload->target_hub_id ?: 'local',
                'size_bytes' => number_format((int) $upload->size_bytes),
                'transferred_bytes' => number_format((int) $upload->transferred_bytes),
                'progress_percent' => number_format((float) $upload->transfer_progress_percent, 2).'%',
                'transfer_status' => $upload->transfer_status,
                'last_activity_at' => $upload->last_activity_at?->diffForHumans(),
            ])
            ->values();

        return [$rows, [
            ['key' => 'session_id', 'label' => 'Session ID'],
            ['key' => 'attachment_name', 'label' => 'Attachment'],
            ['key' => 'relay_id', 'label' => 'Relay ID'],
            ['key' => 'target_hub_id', 'label' => 'Target'],
            ['key' => 'size_bytes', 'label' => 'Size'],
            ['key' => 'transferred_bytes', 'label' => 'Transferred'],
            ['key' => 'progress_percent', 'label' => 'Progress'],
            ['key' => 'transfer_status', 'label' => 'Status'],
        ], 'Local and hub-to-hub upload sessions, including large file transfer progress.'];
    }

    private function deadLettersData(): array
    {
        $deliveryRows = HubRelayDelivery::query()
            ->with('message:id,relay_id,message_type')
            ->where('status', HubRelayDelivery::STATUS_DEAD)
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (HubRelayDelivery $delivery): array => [
                'kind' => 'delivery',
                'id' => $delivery->id,
                'subject' => $delivery->message?->relay_id ?? $delivery->hub_relay_message_id,
                'type' => $delivery->message?->message_type ?? 'unknown',
                'status' => $delivery->status,
                'attempt_count' => (int) $delivery->attempt_count,
                'error' => $delivery->last_error ?: 'None',
                'updated_at' => $delivery->updated_at?->diffForHumans(),
            ]);

        $dispatchRows = HubRelayHandlerDispatch::query()
            ->with('message:id,relay_id,message_type')
            ->where('status', HubRelayHandlerDispatch::STATUS_DEAD)
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (HubRelayHandlerDispatch $dispatch): array => [
                'kind' => 'handler_dispatch',
                'id' => $dispatch->id,
                'subject' => $dispatch->message?->relay_id ?? $dispatch->hub_relay_message_id,
                'type' => $dispatch->message?->message_type ?? 'unknown',
                'status' => $dispatch->status,
                'attempt_count' => (int) $dispatch->attempt_count,
                'error' => $dispatch->last_error ?: 'None',
                'updated_at' => $dispatch->updated_at?->diffForHumans(),
            ]);

        $rows = $deliveryRows
            ->concat($dispatchRows)
            ->sortByDesc('updated_at')
            ->values();

        return [$rows, [
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'subject', 'label' => 'Subject'],
            ['key' => 'type', 'label' => 'Message Type'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'attempt_count', 'label' => 'Attempts'],
            ['key' => 'error', 'label' => 'Last Error'],
            ['key' => 'updated_at', 'label' => 'Updated'],
        ], 'Operator-facing queue of relay work that has exhausted retry policy.'];
    }

    private function clientsData(): array
    {
        $rows = HubRelayClient::query()
            ->withCount('handlers')
            ->latest('updated_at')
            ->limit(150)
            ->get()
            ->map(fn (HubRelayClient $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'system_code' => $client->system_code,
                'api_key' => $client->maskedApiKey(),
                'status' => $client->is_active ? 'active' : 'inactive',
                'handlers_count' => (int) $client->handlers_count,
                'last_used_at' => $client->last_used_at?->diffForHumans() ?? 'Never',
                'updated_at' => $client->updated_at?->diffForHumans(),
            ])
            ->values();

        return [$rows, [
            ['key' => 'name', 'label' => 'Client'],
            ['key' => 'system_code', 'label' => 'System Code'],
            ['key' => 'api_key', 'label' => 'API Key'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'handlers_count', 'label' => 'Handlers'],
            ['key' => 'last_used_at', 'label' => 'Last Used'],
            ['key' => 'updated_at', 'label' => 'Updated'],
        ], 'Local application registrations and API-token lifecycle controls.'];
    }

    private function usersData(): array
    {
        $rows = User::query()
            ->latest('updated_at')
            ->limit(150)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->is_active ? 'active' : 'inactive',
                'is_active' => (bool) $user->is_active,
                'last_login_at' => $user->last_login_at?->diffForHumans() ?? 'Never',
                'updated_at' => $user->updated_at?->diffForHumans(),
                'toggle_active_url' => '/relay/users/'.$user->id.'/toggle-active',
                'reset_password_url' => '/relay/users/'.$user->id.'/reset-password',
            ])
            ->values();

        return [$rows, [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'role', 'label' => 'Role'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'last_login_at', 'label' => 'Last Login'],
            ['key' => 'updated_at', 'label' => 'Updated'],
        ], 'Relay operator accounts, roles, and access status.'];
    }
}
