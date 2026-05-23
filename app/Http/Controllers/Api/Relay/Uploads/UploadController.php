<?php

namespace App\Http\Controllers\Api\Relay\Uploads;

use App\Http\Controllers\Controller;
use App\Models\HubRelayUploadSession;
use App\Relay\Uploads\RelayUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function __construct(
        private RelayUploadService $uploads,
    ) {}

    /**
     * Upload chunk (local app)
     */
    public function chunk(Request $request, HubRelayUploadSession $session): JsonResponse
    {
        return $this->handleChunkUpload($request, $session);
    }

    /**
     * Complete upload (local app)
     */
    public function complete(Request $request, HubRelayUploadSession $session): JsonResponse
    {
        return $this->handleCompleteUpload($request, $session);
    }

    /**
     * Get upload status (local app)
     */
    public function show(Request $request, HubRelayUploadSession $session): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->uploads->getStatus($session),
        ]);
    }

    /**
     * Initialize hub-to-hub upload
     */
    public function initUpload(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'relay_id' => 'required|string',
                'source_hub_id' => 'required|string',
                'target_hub_id' => 'nullable|string',
                'attachment_type' => 'required|string|in:file,image,binary',
                'attachment_name' => 'required|string',
                'mime_type' => 'required|string',
                'size_bytes' => 'required|integer|min:1',
                'checksum' => 'nullable|string',
                'chunk_size_bytes' => 'nullable|integer|min:1',
            ]);

            return response()->json([
                'success' => true,
                ...$this->uploads->initInboundUpload($validated),
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
        }
    }

    /**
     * Receive chunk (hub-to-hub)
     */
    public function receiveChunk(Request $request): JsonResponse
    {
        $session = HubRelayUploadSession::query()->find($request->input('upload_session_id'));

        if ($session === null) {
            return response()->json([
                'success' => false,
                'error' => 'Upload session not found',
            ], 404);
        }

        return $this->handleChunkUpload($request, $session);
    }

    /**
     * Complete hub-to-hub upload
     */
    public function completeUpload(Request $request): JsonResponse
    {
        $session = HubRelayUploadSession::query()->find($request->input('upload_session_id'));

        if ($session === null) {
            return response()->json([
                'success' => false,
                'error' => 'Upload session not found',
            ], 404);
        }

        return $this->handleCompleteUpload($request, $session);
    }

    /**
     * Check upload status (hub-to-hub)
     */
    public function uploadStatus(Request $request, HubRelayUploadSession $session): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->uploads->getStatus($session),
        ]);
    }

    private function handleChunkUpload(Request $request, HubRelayUploadSession $session): JsonResponse
    {
        try {
            $validated = $request->validate([
                'chunk_index' => 'required|integer|min:0',
                'total_chunks' => 'required|integer|min:1',
                'chunk_checksum' => 'nullable|string',
            ]);

            return response()->json([
                'success' => true,
                ...$this->uploads->appendChunk(
                    $session,
                    (int) $validated['chunk_index'],
                    (int) $validated['total_chunks'],
                    $validated['chunk_checksum'] ?? null,
                    (string) $request->getContent(),
                ),
            ]);
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
        }
    }

    private function handleCompleteUpload(Request $request, HubRelayUploadSession $session): JsonResponse
    {
        try {
            $validated = $request->validate([
                'total_chunks' => 'required|integer|min:1',
                'final_checksum' => 'nullable|string',
            ]);

            return response()->json([
                'success' => true,
                ...$this->uploads->completeUpload(
                    $session,
                    (int) $validated['total_chunks'],
                    $validated['final_checksum'] ?? null,
                ),
            ]);
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
        }
    }
}
