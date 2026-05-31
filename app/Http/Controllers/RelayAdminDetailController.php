<?php

namespace App\Http\Controllers;

use App\Models\HubRelayDelivery;
use App\Models\HubRelayClient;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayUploadSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

class RelayAdminDetailController extends Controller
{
    public function message(HubRelayMessage $message): View
    {
        return view('relay.admin-detail', [
            'appName' => config('app.name'),
            'detailMode' => 'generic',
            'activeNav' => 'outbox',
            'title' => 'Message Detail',
            'subtitle' => 'Loading',
            'backUrl' => '/relay/outbox',
            'backLabel' => 'Back to Outbox',
            'dataUrl' => '/relay/data/messages/'.$message->id,
        ]);
    }

    public function delivery(HubRelayDelivery $delivery): View
    {
        return view('relay.admin-detail', [
            'appName' => config('app.name'),
            'detailMode' => 'generic',
            'activeNav' => 'deliveries',
            'title' => 'Delivery Detail',
            'subtitle' => 'Loading',
            'backUrl' => '/relay/deliveries',
            'backLabel' => 'Back to Deliveries',
            'dataUrl' => '/relay/data/deliveries/'.$delivery->id,
        ]);
    }

    public function upload(HubRelayUploadSession $upload): View
    {
        return view('relay.admin-detail', [
            'appName' => config('app.name'),
            'detailMode' => 'generic',
            'activeNav' => 'uploads',
            'title' => 'Upload Detail',
            'subtitle' => 'Loading',
            'backUrl' => '/relay/uploads',
            'backLabel' => 'Back to Uploads',
            'dataUrl' => '/relay/data/uploads/'.$upload->id,
        ]);
    }

    public function handlerDispatch(HubRelayHandlerDispatch $dispatch): View
    {
        return view('relay.admin-detail', [
            'appName' => config('app.name'),
            'detailMode' => 'generic',
            'activeNav' => 'dead-letters',
            'title' => 'Handler Dispatch Detail',
            'subtitle' => 'Loading',
            'backUrl' => '/relay/dead-letters',
            'backLabel' => 'Back to Dead Letters',
            'dataUrl' => '/relay/data/handler-dispatches/'.$dispatch->id,
        ]);
    }

    public function client(HubRelayClient $client): View
    {
        return view('relay.admin-detail', [
            'appName' => config('app.name'),
            'detailMode' => 'client',
            'activeNav' => 'clients',
            'title' => 'Client Detail',
            'subtitle' => 'Loading',
            'backUrl' => '/relay/clients',
            'backLabel' => 'Back to Clients',
            'dataUrl' => '/relay/data/clients/'.$client->id,
        ]);
    }

    public function user(User $user): View
    {
        return view('relay.admin-detail', [
            'appName' => config('app.name'),
            'detailMode' => 'generic',
            'activeNav' => 'users',
            'title' => 'User Detail',
            'subtitle' => 'Loading',
            'backUrl' => '/relay/users',
            'backLabel' => 'Back to Users',
            'dataUrl' => '/relay/data/users/'.$user->id,
        ]);
    }

    public function messageData(HubRelayMessage $message): JsonResponse
    {
        $message->load(['deliveries', 'attachments', 'receipt']);

        return response()->json($this->detailPayload(
            'Message Detail',
            $message->relay_id,
            [
                ['label' => 'Message Type', 'value' => $message->message_type],
                ['label' => 'Origin HQ Hub', 'value' => $message->origin_hq_hub_id ?: 'unknown'],
                ['label' => 'Source Hub', 'value' => $message->source_hub_id],
                ['label' => 'Source System', 'value' => $message->source_system],
                ['label' => 'Target Systems', 'value' => collect($message->allTargetSystems())->implode(', ') ?: 'None'],
                ['label' => 'Target Hubs', 'value' => collect($message->targetHubIds())->implode(', ') ?: 'None'],
                ['label' => 'Priority', 'value' => $message->priority],
                ['label' => 'Created', 'value' => $message->created_at?->toIso8601String()],
            ],
            $message,
            [
                'Deliveries' => $message->deliveries->map(fn (HubRelayDelivery $delivery): array => [
                    'label' => ($delivery->target_hq_hub_id ?: $delivery->target_hub_id).' • '.$delivery->status,
                    'href' => '/relay/delivery/'.$delivery->id,
                ])->values()->all(),
            ],
        ));
    }

    public function deliveryData(HubRelayDelivery $delivery): JsonResponse
    {
        $delivery->load('message');

        return response()->json($this->detailPayload(
            'Delivery Detail',
            $delivery->id,
            [
                ['label' => 'Relay ID', 'value' => $delivery->message?->relay_id],
                ['label' => 'Target HQ Hub', 'value' => $delivery->target_hq_hub_id ?: $delivery->target_hub_id],
                ['label' => 'Target Systems', 'value' => collect($delivery->message?->allTargetSystems() ?? [])->implode(', ') ?: 'None'],
                ['label' => 'Status', 'value' => $delivery->status],
                ['label' => 'Attempts', 'value' => (string) $delivery->attempt_count],
                ['label' => 'Next Retry', 'value' => $delivery->next_retry_at?->toIso8601String() ?? 'Not scheduled'],
            ],
            $delivery,
            [
                'Parent Message' => array_filter([
                    $delivery->message ? [
                        'label' => $delivery->message->relay_id,
                        'href' => '/relay/messages/'.$delivery->message->id,
                    ] : null,
                ]),
            ],
            [
                [
                    'label' => 'Retry Delivery',
                    'action' => '/relay/delivery/'.$delivery->id.'/retry',
                    'method' => 'POST',
                    'tone' => 'primary',
                    'fetch' => true,
                    'visibleWhen' => in_array($delivery->status, [HubRelayDelivery::STATUS_FAILED, HubRelayDelivery::STATUS_DEAD], true),
                    'confirm' => [
                        'title' => 'Retry Delivery',
                        'body' => 'This will requeue the delivery and reset its retry state for processing.',
                        'confirmLabel' => 'Retry Delivery',
                    ],
                ],
                [
                    'label' => 'Cancel Delivery',
                    'action' => '/relay/delivery/'.$delivery->id.'/cancel',
                    'method' => 'POST',
                    'tone' => 'danger',
                    'fetch' => true,
                    'visibleWhen' => $delivery->status !== HubRelayDelivery::STATUS_DELIVERED,
                    'confirm' => [
                        'title' => 'Cancel Delivery',
                        'body' => 'This will stop delivery attempts and mark the delivery as dead.',
                        'confirmLabel' => 'Cancel Delivery',
                    ],
                ],
            ],
        ));
    }

    public function uploadData(HubRelayUploadSession $upload): JsonResponse
    {
        $upload->load(['message', 'attachment']);

        return response()->json($this->detailPayload(
            'Upload Detail',
            $upload->id,
            [
                ['label' => 'Attachment', 'value' => $upload->attachment_name],
                ['label' => 'Direction', 'value' => $upload->direction],
                ['label' => 'Status', 'value' => $upload->transfer_status],
                ['label' => 'Progress', 'value' => number_format((float) $upload->transfer_progress_percent, 2).'%'],
                ['label' => 'Target Hub', 'value' => $upload->target_hub_id ?: 'local'],
            ],
            $upload,
            [
                'Parent Message' => array_filter([
                    $upload->message ? [
                        'label' => $upload->message->relay_id,
                        'href' => '/relay/messages/'.$upload->message->id,
                    ] : null,
                ]),
            ],
        ));
    }

    public function handlerDispatchData(HubRelayHandlerDispatch $dispatch): JsonResponse
    {
        $dispatch->load(['handler', 'message', 'receipt']);

        return response()->json($this->detailPayload(
            'Handler Dispatch Detail',
            $dispatch->id,
            [
                ['label' => 'Handler', 'value' => $dispatch->handler?->name],
                ['label' => 'Relay ID', 'value' => $dispatch->message?->relay_id],
                ['label' => 'Status', 'value' => $dispatch->status],
                ['label' => 'Attempts', 'value' => (string) $dispatch->attempt_count],
                ['label' => 'Next Retry', 'value' => $dispatch->next_retry_at?->toIso8601String() ?? 'Not scheduled'],
            ],
            $dispatch,
            [
                'Handler Client' => array_filter([
                    $dispatch->handler ? [
                        'label' => $dispatch->handler->name,
                        'href' => '/relay/client/'.$dispatch->handler->hub_relay_client_id,
                    ] : null,
                ]),
                'Parent Message' => array_filter([
                    $dispatch->message ? [
                        'label' => $dispatch->message->relay_id,
                        'href' => '/relay/messages/'.$dispatch->message->id,
                    ] : null,
                ]),
            ],
            [
                [
                    'label' => 'Retry Handler Dispatch',
                    'action' => '/relay/handler-dispatch/'.$dispatch->id.'/retry',
                    'method' => 'POST',
                    'tone' => 'primary',
                    'fetch' => true,
                    'visibleWhen' => in_array($dispatch->status, [HubRelayHandlerDispatch::STATUS_FAILED, HubRelayHandlerDispatch::STATUS_DEAD], true),
                    'confirm' => [
                        'title' => 'Retry Handler Dispatch',
                        'body' => 'This will requeue the handler dispatch and clear its current failure state.',
                        'confirmLabel' => 'Retry Handler Dispatch',
                    ],
                ],
            ],
        ));
    }

    public function clientData(HubRelayClient $client): JsonResponse
    {
        $client->load(['handlers', 'handlers.client']);

        return response()->json($this->detailPayload(
            'Client Detail',
            $client->system_code,
            [
                ['label' => 'Client', 'value' => $client->name],
                ['label' => 'System Code', 'value' => $client->system_code],
                ['label' => 'Status', 'value' => $client->is_active ? 'active' : 'inactive'],
                ['label' => 'API Key', 'value' => $client->maskedApiKey()],
                ['label' => 'Last Used', 'value' => $client->last_used_at?->toIso8601String() ?? 'Never'],
            ],
            $client,
            [
                'Handlers' => $client->handlers->map(fn ($handler): array => [
                    'label' => $handler->name.' • '.$handler->message_type_pattern,
                    'href' => null,
                ])->values()->all(),
            ],
            [
                [
                    'id' => 'rotate-api-key',
                    'label' => 'Rotate API Key',
                    'action' => '/relay/clients/'.$client->id.'/rotate-key',
                    'method' => 'POST',
                    'tone' => 'primary',
                    'adminOnly' => true,
                    'visibleWhen' => true,
                    'confirm' => [
                        'title' => 'Rotate API Key',
                        'body' => 'Rotate this client API key now? The current key will stop working immediately.',
                        'confirmLabel' => 'Rotate API Key',
                    ],
                ],
                [
                    'id' => 'toggle-client-active',
                    'label' => $client->is_active ? 'Deactivate Client' : 'Reactivate Client',
                    'action' => '/relay/clients/'.$client->id.'/toggle-active',
                    'method' => 'POST',
                    'tone' => $client->is_active ? 'danger' : 'ghost',
                    'adminOnly' => true,
                    'visibleWhen' => true,
                    'confirm' => [
                        'title' => $client->is_active ? 'Deactivate Client' : 'Reactivate Client',
                        'body' => $client->is_active
                            ? 'Deactivate this client now? Its token will no longer be accepted until reactivated.'
                            : 'Reactivate this client now so it can submit and manage relay traffic again.',
                        'confirmLabel' => $client->is_active ? 'Deactivate Client' : 'Reactivate Client',
                    ],
                ],
            ],
            [
                'title' => 'Client Handlers',
                'canManage' => (bool) auth()->user()?->isRelayAdmin(),
                'createUrl' => '/relay/clients/'.$client->id.'/handlers',
                'toggleLabel' => $client->handlers->isEmpty() ? '0 handlers' : $client->handlers->count().' handler(s)',
                'rows' => $client->handlers
                    ->sortByDesc('updated_at')
                    ->values()
                    ->map(fn ($handler): array => [
                        'id' => $handler->id,
                        'name' => $handler->name,
                        'endpoint_url' => $handler->endpoint_url,
                        'message_type_pattern' => $handler->message_type_pattern,
                        'source_system' => $handler->source_system ?: 'Any',
                        'source_hub_id' => $handler->source_hub_id ?: 'Any',
                        'auth_token' => $handler->getRawOriginal('auth_token') ?? '',
                        'auth_token_set' => filled($handler->getRawOriginal('auth_token')),
                        'status' => $handler->is_active ? 'active' : 'inactive',
                        'is_active' => (bool) $handler->is_active,
                        'last_dispatched_at' => $handler->last_dispatched_at?->toIso8601String() ?? 'Never',
                        'updated_at' => $handler->updated_at?->diffForHumans() ?? 'Unknown',
                        'update_url' => '/relay/clients/'.$client->id.'/handlers/'.$handler->id,
                        'toggle_active_url' => '/relay/clients/'.$client->id.'/handlers/'.$handler->id.'/toggle-active',
                    ])
                    ->all(),
            ],
        ));
    }

    public function userData(User $user): JsonResponse
    {
        return response()->json($this->detailPayload(
            'User Detail',
            $user->email,
            [
                ['label' => 'Name', 'value' => $user->name],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Role', 'value' => $user->role],
                ['label' => 'Status', 'value' => $user->is_active ? 'active' : 'inactive'],
                ['label' => 'Last Login', 'value' => $user->last_login_at?->toIso8601String() ?? 'Never'],
            ],
            $user,
            [],
            [
                [
                    'label' => $user->role === User::ROLE_ADMIN ? 'Set As Operator' : 'Set As Admin',
                    'action' => '/relay/users/'.$user->id.'/role',
                    'method' => 'POST',
                    'tone' => 'primary',
                    'fetch' => true,
                    'adminOnly' => true,
                    'visibleWhen' => true,
                    'confirm' => [
                        'title' => $user->role === User::ROLE_ADMIN ? 'Set User As Operator' : 'Set User As Admin',
                        'body' => $user->role === User::ROLE_ADMIN
                            ? 'This will remove admin access from this user and set them as an operator.'
                            : 'This will grant admin access to this user.',
                        'confirmLabel' => $user->role === User::ROLE_ADMIN ? 'Set As Operator' : 'Set As Admin',
                    ],
                    'fields' => [
                        ['name' => 'role', 'value' => $user->role === User::ROLE_ADMIN ? User::ROLE_OPERATOR : User::ROLE_ADMIN],
                    ],
                ],
                [
                    'label' => $user->is_active ? 'Deactivate User' : 'Reactivate User',
                    'action' => '/relay/users/'.$user->id.'/toggle-active',
                    'method' => 'POST',
                    'tone' => $user->is_active ? 'danger' : 'ghost',
                    'fetch' => true,
                    'adminOnly' => true,
                    'visibleWhen' => true,
                    'confirm' => [
                        'title' => $user->is_active ? 'Deactivate User' : 'Reactivate User',
                        'body' => $user->is_active
                            ? 'This user will lose access to the relay operator console until reactivated.'
                            : 'This user will regain access to the relay operator console.',
                        'confirmLabel' => $user->is_active ? 'Deactivate User' : 'Reactivate User',
                    ],
                ],
            ],
        ));
    }

    private function detailPayload(
        string $title,
        string $subtitle,
        array $summary,
        mixed $inspector,
        array $relatedRecords = [],
        array $actions = [],
        ?array $extra = null,
    ): array {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'summary' => $summary,
            'inspector' => $inspector,
            'relatedRecords' => $relatedRecords,
            'actions' => $actions,
            'extra' => $extra,
        ];
    }
}
