<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Relay\DiagnosticsController;
use App\Http\Controllers\Api\Relay\Messages\MessageController;
use App\Http\Controllers\Api\Relay\Messages\DeliveryController;
use App\Http\Controllers\Api\Relay\Inbound\ReceiveController;
use App\Http\Controllers\Api\Relay\Inbound\InboxController;
use App\Http\Controllers\Api\Relay\Handlers\HandlerController;
use App\Http\Controllers\Api\Relay\Handlers\HandlerDispatchController;
use App\Http\Controllers\Api\Relay\Attachments\AttachmentController;
use App\Http\Controllers\Api\Relay\Uploads\UploadController;

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Shared lightweight hub heartbeat endpoint
Route::get('/status', [DiagnosticsController::class, 'status']);

// API version and diagnostics (no authentication required for phase 1)
Route::middleware('relay.protocol')->prefix('v1')->group(function () {
    Route::get('/diagnostics', [DiagnosticsController::class, 'index']);
    Route::get('/compatibility', [DiagnosticsController::class, 'compatibility']);
});

// Local Application API
Route::middleware(['relay.protocol', 'relay.client'])->prefix('v1')->group(function () {
    // Message submission and retrieval
    Route::post('/messages', [MessageController::class, 'store']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{message}', [MessageController::class, 'show']);
    Route::get('/inbox', [InboxController::class, 'index']);
    Route::get('/inbox/{message}', [InboxController::class, 'show']);

    // Delivery tracking
    Route::get('/deliveries', [DeliveryController::class, 'index']);
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);
    Route::post('/deliveries/{delivery}/retry', [DeliveryController::class, 'retry']);
    Route::post('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel']);

    // Attachments
    Route::post('/messages/{message}/attachments/init', [AttachmentController::class, 'init']);
    Route::post('/attachments/{attachment}/complete', [AttachmentController::class, 'complete']);

    // Uploads (chunked)
    Route::post('/uploads/{session}/chunk', [UploadController::class, 'chunk']);
    Route::post('/uploads/{session}/complete', [UploadController::class, 'complete']);
    Route::get('/uploads/{session}', [UploadController::class, 'show']);

    // Local handler registration
    Route::get('/handlers', [HandlerController::class, 'index']);
    Route::post('/handlers', [HandlerController::class, 'store']);
    Route::patch('/handlers/{handler}', [HandlerController::class, 'update']);
    Route::delete('/handlers/{handler}', [HandlerController::class, 'destroy']);
    Route::get('/handler-dispatches', [HandlerDispatchController::class, 'index']);
    Route::get('/handler-dispatches/{dispatch}', [HandlerDispatchController::class, 'show']);
    Route::post('/handler-dispatches/{dispatch}/retry', [HandlerDispatchController::class, 'retry']);
});

// Hub-to-Hub Relay API (no local auth, uses hub authentication)
Route::middleware(['relay.protocol', 'relay.hub'])->prefix('v1')->group(function () {
    // Inbound receive
    Route::post('/receive', [ReceiveController::class, 'store']);
    Route::post('/receive-batch', [ReceiveController::class, 'storeBatch']);

    // Upload handling
    Route::post('/upload/init', [UploadController::class, 'initUpload']);
    Route::post('/upload/chunk', [UploadController::class, 'receiveChunk']);
    Route::post('/upload/complete', [UploadController::class, 'completeUpload']);
    Route::get('/upload/{session}/status', [UploadController::class, 'uploadStatus']);
});
