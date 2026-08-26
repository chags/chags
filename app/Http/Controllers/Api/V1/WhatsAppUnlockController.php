<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestWhatsAppCodeRequest;
use App\Http\Requests\Api\V1\VerifyWhatsAppCodeRequest;
use App\Models\WhatsAppUnlockChallenge;
use App\Services\MobileApi\WhatsAppCodeService;
use Illuminate\Http\JsonResponse;

class WhatsAppUnlockController extends Controller
{
    public function requestCode(RequestWhatsAppCodeRequest $request, WhatsAppCodeService $service): JsonResponse
    {
        $challenge = $service->request(
            $request->string('phone')->toString(),
            $request->string('device_installation_id')->toString(),
            $request,
        );

        return response()->json([
            'message' => 'Se o telefone estiver cadastrado, enviaremos um código.',
            'data' => [
                'challenge_id' => $challenge->id,
                'expires_in' => config('mobile-api.whatsapp.code_ttl_seconds'),
                'resend_after' => config('mobile-api.whatsapp.resend_seconds'),
            ],
        ], 202);
    }

    public function verifyCode(VerifyWhatsAppCodeRequest $request, WhatsAppCodeService $service): JsonResponse
    {
        $challenge = WhatsAppUnlockChallenge::query()->findOrFail($request->string('challenge_id'));
        $user = $service->verify(
            $challenge,
            $request->string('device_installation_id')->toString(),
            $request->string('code')->toString(),
        );
        $requiresFace = config('mobile-api.faceio.enabled') && ! $user->first_app_access_completed_at;
        $token = auth('api')->claims([
            'app_unlocked' => ! $requiresFace,
            'unlock_method' => 'whatsapp_otp',
            'installation_id' => $challenge->device_installation_id,
        ])->fromUser($user);

        return response()->json(['data' => [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'app_unlocked' => ! $requiresFace,
            'first_access' => ! $user->first_app_access_completed_at,
            'requires_face_enrollment' => $requiresFace,
        ]]);
    }
}
