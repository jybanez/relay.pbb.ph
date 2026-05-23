<?php

use App\Installer\InstallerMode;
use App\Http\Controllers\Installer\InstallerController;
use App\Http\Controllers\RelayAccountController;
use App\Http\Controllers\RelayDashboardController;
use App\Http\Controllers\RelayPublicHomeController;
use App\Http\Controllers\RelayAdminSectionController;
use App\Http\Controllers\RelayAdminDetailController;
use App\Http\Controllers\RelayAuthController;
use App\Http\Controllers\RelayClientAdminController;
use App\Http\Controllers\RelayOperatorActionController;
use App\Http\Controllers\RelaySwaggerController;
use App\Http\Controllers\RelayUserAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function (
    InstallerMode $installerMode,
    InstallerController $installerController,
    RelayPublicHomeController $relayPublicHomeController,
) {
    if ($installerMode->shouldServeInstaller()) {
        return $installerController->show();
    }

    return $relayPublicHomeController();
});

Route::get('/install', [InstallerController::class, 'show']);
Route::get('/install/api/environment', [InstallerController::class, 'environment']);
Route::post('/install/api/environment/continue', [InstallerController::class, 'continueEnvironment']);
Route::post('/install/api/hq/validate', [InstallerController::class, 'validateHq']);
Route::post('/install/api/settings', [InstallerController::class, 'saveSettings']);
Route::post('/install/api/execute', [InstallerController::class, 'execute']);
Route::get('/install/api/progress', [InstallerController::class, 'executionProgress']);
Route::post('/install/api/execute/advance', [InstallerController::class, 'advanceExecution']);
Route::post('/install/api/execute/retry', [InstallerController::class, 'retryExecution']);
Route::post('/install/api/cleanup', [InstallerController::class, 'cleanup']);

Route::get('/relay/login', [RelayAuthController::class, 'showLogin'])->name('login');
Route::post('/relay/login', [RelayAuthController::class, 'login']);
Route::get('/api/bootstrap', [RelayAuthController::class, 'bootstrap']);
Route::get('/api/csrf-token', [RelayAuthController::class, 'csrfToken']);
Route::get('/api/user', [RelayAuthController::class, 'currentUser']);
Route::post('/api/login', [RelayAuthController::class, 'apiLogin']);
Route::post('/api/logout', [RelayAuthController::class, 'apiLogout']);
Route::get('/relay/api/docs', RelaySwaggerController::class);

Route::middleware(['auth'])->group(function (): void {
    Route::get('/api/session/ping', [RelayAuthController::class, 'sessionPing']);
});

Route::middleware(['auth', 'relay.operator'])->group(function (): void {
    Route::post('/api/user', [RelayAccountController::class, 'updateProfile']);
    Route::post('/api/user/password', [RelayAccountController::class, 'updatePassword']);
    Route::post('/api/account/profile', [RelayAccountController::class, 'updateProfile']);
    Route::post('/api/account/password', [RelayAccountController::class, 'updatePassword']);
    Route::get('/relay', RelayDashboardController::class);
    Route::get('/relay/data/dashboard', [RelayDashboardController::class, 'data']);
    Route::post('/relay/logout', [RelayAuthController::class, 'logout']);
    Route::get('/relay/data/sections/{section}', [RelayAdminSectionController::class, 'data'])
        ->where('section', 'outbox|inbox|deliveries|uploads|dead-letters|clients|users');
    Route::get('/relay/data/messages/{message}', [RelayAdminDetailController::class, 'messageData']);
    Route::get('/relay/data/deliveries/{delivery}', [RelayAdminDetailController::class, 'deliveryData']);
    Route::get('/relay/data/uploads/{upload}', [RelayAdminDetailController::class, 'uploadData']);
    Route::get('/relay/data/handler-dispatches/{dispatch}', [RelayAdminDetailController::class, 'handlerDispatchData']);
    Route::get('/relay/data/clients/{client}', [RelayAdminDetailController::class, 'clientData']);
    Route::get('/relay/data/users/{user}', [RelayAdminDetailController::class, 'userData']);
    Route::get('/relay/{section}', RelayAdminSectionController::class)
        ->where('section', 'outbox|inbox|deliveries|uploads|dead-letters|clients|users');
    Route::get('/relay/messages/{message}', [RelayAdminDetailController::class, 'message']);
    Route::get('/relay/delivery/{delivery}', [RelayAdminDetailController::class, 'delivery']);
    Route::get('/relay/upload/{upload}', [RelayAdminDetailController::class, 'upload']);
    Route::get('/relay/handler-dispatch/{dispatch}', [RelayAdminDetailController::class, 'handlerDispatch']);
    Route::get('/relay/client/{client}', [RelayAdminDetailController::class, 'client']);
    Route::get('/relay/user/{user}', [RelayAdminDetailController::class, 'user']);
    Route::post('/relay/delivery/{delivery}/retry', [RelayOperatorActionController::class, 'retryDelivery']);
    Route::post('/relay/delivery/{delivery}/cancel', [RelayOperatorActionController::class, 'cancelDelivery']);
    Route::post('/relay/handler-dispatch/{dispatch}/retry', [RelayOperatorActionController::class, 'retryHandlerDispatch']);
    Route::post('/relay/clients', [RelayClientAdminController::class, 'store']);
    Route::post('/relay/clients/{client}/rotate-key', [RelayClientAdminController::class, 'rotateKey']);
    Route::post('/relay/clients/{client}/toggle-active', [RelayClientAdminController::class, 'toggleActive']);
    Route::post('/relay/clients/{client}/handlers', [RelayClientAdminController::class, 'storeHandler']);
    Route::post('/relay/clients/{client}/handlers/{handler}', [RelayClientAdminController::class, 'updateHandler']);
    Route::post('/relay/clients/{client}/handlers/{handler}/toggle-active', [RelayClientAdminController::class, 'toggleHandlerActive']);
    Route::post('/relay/users', [RelayUserAdminController::class, 'store']);
    Route::post('/relay/users/{user}/role', [RelayUserAdminController::class, 'setRole']);
    Route::post('/relay/users/{user}/toggle-active', [RelayUserAdminController::class, 'toggleActive']);
    Route::post('/relay/users/{user}/reset-password', [RelayUserAdminController::class, 'resetPassword']);
});
