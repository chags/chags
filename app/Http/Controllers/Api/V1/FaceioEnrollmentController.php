<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiDevice;
use App\Models\FaceioSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FaceioEnrollmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(config('mobile-api.faceio.enabled'), 503, 'FACEIO não está habilitado.');
        $device = ApiDevice::query()->whereKey($request->header('X-Device-ID'))->where('user_id', $request->user()->id)->firstOrFail();
        $payload = (string) Str::ulid().'.'.Str::random(48);
        $session = FaceioSession::query()->create([
            'user_id' => $request->user()->id,
            'api_device_id' => $device->id,
            'opaque_payload_hash' => hash('sha256', $payload),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json(['data' => [
            'session_id' => $session->id,
            'faceio_public_id' => config('mobile-api.faceio.public_id'),
            'payload' => $payload,
            'expires_at' => $session->expires_at->toIso8601String(),
        ]], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate(['session_id' => ['required', 'exists:faceio_sessions,id']]);
        $session = FaceioSession::query()->whereKey($validated['session_id'])->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($session->status === 'confirmed' && $session->confirmed_at && $session->expires_at->isFuture(), 422, 'Cadastro facial ainda não foi confirmado.');
        $session->update(['consumed_at' => now()]);
        $device = ApiDevice::query()->findOrFail($session->api_device_id);
        $token = auth('api')->claims([
            'app_unlocked' => true,
            'unlock_method' => 'whatsapp_otp_faceio',
            'device_id' => $device->id,
            'installation_id' => $device->installation_id,
        ])->fromUser($request->user());

        return response()->json(['data' => ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => auth('api')->factory()->getTTL() * 60, 'app_unlocked' => true]]);
    }
}
