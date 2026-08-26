<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateDeviceChallengeRequest;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Http\Requests\Api\V1\VerifyDeviceRequest;
use App\Models\ApiDevice;
use App\Models\DeviceChallenge;
use App\Services\MobileApi\DeviceChallengeService;
use App\Services\MobileApi\DeviceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeviceController extends Controller
{
    public function challenge(CreateDeviceChallengeRequest $request, DeviceChallengeService $service): JsonResponse
    {
        Log::info('Mobile device challenge requested.', [
            'user_id' => $request->user()->id,
            'installation_id' => $request->string('installation_id')->toString(),
            'platform' => $request->string('platform')->toString(),
            'purpose' => $request->string('purpose')->toString(),
            'ip' => $request->ip(),
        ]);

        $created = $service->create(
            $request->user(),
            $request->string('installation_id')->toString(),
            $request->string('purpose')->toString(),
            $request->ip(),
        );

        return response()->json(['data' => [
            'challenge_id' => $created['challenge']->id,
            'nonce' => $created['nonce'],
            'expires_at' => $created['challenge']->expires_at->toIso8601String(),
            'required_attestation' => $request->string('platform')->toString() === 'ios' ? 'app_attest' : 'play_integrity',
        ]], 201);
    }

    public function register(RegisterDeviceRequest $request, DeviceRegistrationService $service): JsonResponse
    {
        $context = [
            'user_id' => $request->user()->id,
            'challenge_id' => $request->string('challenge_id')->toString(),
            'installation_id' => $request->string('installation_id')->toString(),
            'platform' => $request->input('device.platform'),
            'app_version' => $request->input('app.version'),
            'app_build' => $request->input('app.build'),
            'ip' => $request->ip(),
        ];

        Log::info('Mobile device registration started.', $context);

        try {
            $challenge = DeviceChallenge::query()->findOrFail($request->string('challenge_id'));
            $device = $service->register($request->user(), $challenge, $request->validated(), $request->ip());
        } catch (Throwable $exception) {
            Log::warning('Mobile device registration failed.', $context + [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Mobile device registration completed.', $context + [
            'device_id' => $device->id,
            'status' => $device->status,
        ]);

        return response()->json(['data' => $this->resource($device)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->apiDevices()->latest('last_seen_at')->get()->map(fn (ApiDevice $device) => $this->resource($device))]);
    }

    public function verify(VerifyDeviceRequest $request, DeviceRegistrationService $service): JsonResponse
    {
        $challenge = DeviceChallenge::query()->findOrFail($request->string('challenge_id'));
        $device = ApiDevice::query()->findOrFail($request->string('device_id'));
        $verified = $service->verify($request->user(), $device, $challenge, $request->validated(), $request->ip());

        return response()->json(['data' => $this->resource($verified)]);
    }

    public function revoke(Request $request, ApiDevice $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $request->user()->id]);

        return response()->json(['message' => 'Dispositivo revogado com sucesso.']);
    }

    private function resource(ApiDevice $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'platform' => $device->platform,
            'manufacturer' => $device->manufacturer,
            'model' => $device->model,
            'status' => $device->status,
            'trusted' => $device->status === 'trusted',
            'face_verification_required' => $device->status === 'face_verification_required',
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ];
    }
}
