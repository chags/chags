<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\FaceioEnrollmentController;
use App\Http\Controllers\Api\V1\FaceioWebhookController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\TimeAdjustmentController;
use App\Http\Controllers\Api\V1\TimeCardController;
use App\Http\Controllers\Api\V1\TimePunchController;
use App\Http\Controllers\Api\V1\WhatsAppUnlockController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('app-unlock/whatsapp/request', [WhatsAppUnlockController::class, 'requestCode'])
        ->middleware('throttle:5,1');
    Route::post('app-unlock/whatsapp/verify', [WhatsAppUnlockController::class, 'verifyCode'])
        ->middleware('throttle:10,1');
    Route::post('webhooks/faceio', FaceioWebhookController::class)->middleware('throttle:60,1');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', MeController::class);
        Route::post('devices/challenges', [DeviceController::class, 'challenge']);
        Route::post('devices/register', [DeviceController::class, 'register']);
        Route::post('devices/verify', [DeviceController::class, 'verify']);
        Route::get('devices', [DeviceController::class, 'index']);
        Route::delete('devices/{device}', [DeviceController::class, 'revoke']);
        Route::post('face-auth/enrollment-sessions', [FaceioEnrollmentController::class, 'store']);
        Route::post('face-auth/confirm', [FaceioEnrollmentController::class, 'confirm']);

        Route::middleware(['tracks.time', 'app.unlocked', 'device.trusted'])->group(function (): void {
            Route::get('time-punch/status', [TimePunchController::class, 'status']);
            Route::post('time-punch', [TimePunchController::class, 'store'])->middleware('throttle:10,1');
            Route::get('time-card', TimeCardController::class);
            Route::get('time-adjustments', [TimeAdjustmentController::class, 'index']);
            Route::post('time-adjustments', [TimeAdjustmentController::class, 'store']);
            Route::get('time-adjustments/{adjustment}', [TimeAdjustmentController::class, 'show']);
        });
    });
});
