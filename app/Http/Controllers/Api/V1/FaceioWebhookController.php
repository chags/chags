<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiDevice;
use App\Models\FaceioIdentity;
use App\Models\FaceioSession;
use App\Models\IntegrationWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceioWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('mobile-api.faceio.webhook_token');
        $provided = (string) preg_replace('/^Bearer\s+/i', '', $request->header('WWW-Authenticate', ''));
        abort_unless($expected !== '' && hash_equals($expected, $provided), 401, 'Webhook não autenticado.');
        abort_unless($request->string('appId')->toString() === config('mobile-api.faceio.public_id'), 401, 'Aplicação FACEIO inválida.');

        $eventType = $request->string('eventName')->toString();
        abort_unless($eventType === 'ENROLL', 202);
        $payload = $request->input('payload');
        $facialId = $request->string('facialId')->toString();
        abort_unless(is_string($payload) && $payload !== '' && $facialId !== '', 422, 'Evento FACEIO incompleto.');
        $eventId = hash('sha256', $eventType.'|'.$facialId.'|'.json_encode($request->all()));
        $event = IntegrationWebhookEvent::query()->firstOrCreate(
            ['provider' => 'faceio', 'external_event_id' => $eventId],
            ['event_type' => $eventType, 'payload_hash' => hash('sha256', json_encode($request->all())), 'received_at' => now()],
        );
        if ($event->processed_at) {
            return response()->json(['message' => 'Evento já processado.']);
        }

        $session = FaceioSession::query()->where('opaque_payload_hash', hash('sha256', $payload))->where('status', 'pending')->firstOrFail();
        abort_unless($session->expires_at->isFuture(), 422, 'Sessão FACEIO expirada.');
        $facialHash = hash_hmac('sha256', $facialId, config('app.key'));

        FaceioIdentity::query()->updateOrCreate(['user_id' => $session->user_id], [
            'facial_id_encrypted' => $facialId,
            'facial_id_hash' => $facialHash,
            'enrolled_at' => now(),
            'deleted_at' => null,
        ]);
        $session->update(['facial_id_hash' => $facialHash, 'status' => 'confirmed', 'confirmed_at' => now()]);
        ApiDevice::query()->whereKey($session->api_device_id)->update(['status' => 'trusted', 'risk_level' => 'low', 'face_verified_at' => now()]);
        $session->user()->update(['first_app_access_completed_at' => now()]);

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['message' => 'Evento processado.']);
    }
}
