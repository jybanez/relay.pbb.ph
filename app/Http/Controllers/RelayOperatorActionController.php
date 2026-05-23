<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchRelayToLocalHandler;
use App\Jobs\ProcessRelayDelivery;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayHandlerDispatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelayOperatorActionController extends Controller
{
    public function retryDelivery(Request $request, HubRelayDelivery $delivery): RedirectResponse|JsonResponse
    {
        $this->abortUnlessOperator($request);

        abort_unless(in_array($delivery->status, [
            HubRelayDelivery::STATUS_FAILED,
            HubRelayDelivery::STATUS_DEAD,
        ], true), 422, 'Can only retry failed or dead deliveries.');

        $delivery->forceFill([
            'status' => HubRelayDelivery::STATUS_QUEUED,
            'attempt_count' => 0,
            'last_error' => null,
            'next_retry_at' => null,
            'delivered_at' => null,
        ])->save();

        ProcessRelayDelivery::dispatch($delivery->id)
            ->onQueue((string) config('relay.delivery.queue', 'relay-deliveries'));

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Delivery requeued for processing.',
            ]);
        }

        return redirect('/relay/delivery/'.$delivery->id)
            ->with('status', 'Delivery requeued for processing.');
    }

    public function cancelDelivery(Request $request, HubRelayDelivery $delivery): RedirectResponse|JsonResponse
    {
        $this->abortUnlessOperator($request);

        abort_unless($delivery->status !== HubRelayDelivery::STATUS_DELIVERED, 422, 'Cannot cancel an already delivered message.');

        $delivery->forceFill([
            'status' => HubRelayDelivery::STATUS_DEAD,
            'last_error' => 'Cancelled by relay operator',
            'next_retry_at' => null,
        ])->save();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Delivery marked as dead.',
            ]);
        }

        return redirect('/relay/delivery/'.$delivery->id)
            ->with('status', 'Delivery marked as dead.');
    }

    public function retryHandlerDispatch(Request $request, HubRelayHandlerDispatch $dispatch): RedirectResponse|JsonResponse
    {
        $this->abortUnlessOperator($request);

        abort_unless(in_array($dispatch->status, [
            HubRelayHandlerDispatch::STATUS_FAILED,
            HubRelayHandlerDispatch::STATUS_DEAD,
        ], true), 422, 'Can only retry failed or dead handler dispatches.');

        $dispatch->forceFill([
            'status' => HubRelayHandlerDispatch::STATUS_QUEUED,
            'next_retry_at' => null,
            'failed_at' => null,
            'succeeded_at' => null,
            'last_error' => null,
            'last_response_status' => null,
            'queued_at' => now(),
        ])->save();

        DispatchRelayToLocalHandler::dispatch($dispatch->id)
            ->onQueue((string) config('relay.local_handlers.queue', 'relay-handlers'));

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Handler dispatch requeued.',
            ]);
        }

        return redirect('/relay/handler-dispatch/'.$dispatch->id)
            ->with('status', 'Handler dispatch requeued.');
    }

    private function abortUnlessOperator(Request $request): void
    {
        abort_unless($request->user() !== null, 403);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->isXmlHttpRequest();
    }
}
