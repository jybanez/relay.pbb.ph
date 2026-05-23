<?php

namespace App\Http\Controllers\Api\Relay\Attachments;

use App\Http\Controllers\Controller;
use App\Models\HubRelayAttachment;
use App\Models\HubRelayMessage;
use App\Relay\Uploads\RelayUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttachmentController extends Controller
{
    public function __construct(
        private RelayUploadService $uploads,
    ) {}

    /**
     * Initialize attachment upload
     */
    public function init(Request $request, HubRelayMessage $message): JsonResponse
    {
        try {
            $validated = $request->validate([
                'attachment_type' => 'required|string|in:file,image,binary',
                'attachment_name' => 'required|string',
                'mime_type' => 'required|string',
                'size_bytes' => 'required|integer|min:1',
                'checksum' => 'nullable|string',
                'chunk_size_bytes' => 'nullable|integer|min:1',
                'target_hub_id' => 'nullable|string',
            ]);

            return response()->json([
                'success' => true,
                ...$this->uploads->initLocalUpload($message, $validated),
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
     * Complete attachment upload
     */
    public function complete(Request $request, HubRelayAttachment $attachment): JsonResponse
    {
        try {
            $validated = $request->validate([
                'total_chunks' => 'required|integer|min:1',
                'final_checksum' => 'nullable|string',
            ]);

            return response()->json([
                'success' => true,
                ...$this->uploads->completeAttachment($attachment, $validated),
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
