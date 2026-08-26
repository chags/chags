<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MobileApi\IdempotencyService;
use App\Services\VirtualOffice\TimePunchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimePunchController extends Controller
{
    public function status(Request $request, TimePunchService $service): JsonResponse
    {
        return response()->json(['data' => $this->normalize($service->status($request->user()))]);
    }

    public function store(Request $request, TimePunchService $service, IdempotencyService $idempotency): JsonResponse
    {
        $validated = $request->validate([
            'expected_type' => ['required', 'string', Rule::in(['clock_in', 'break_start', 'break_end', 'clock_out'])],
        ]);
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[A-Za-z0-9._:-]{16,255}$/', $key), 422, 'Informe uma chave de idempotência válida.');
        $result = $idempotency->execute(
            $request->user(),
            'time-punch',
            'POST',
            $key,
            ['device_id' => $request->header('X-Device-ID'), 'expected_type' => $validated['expected_type']],
            fn () => [
                'body' => ['message' => 'Ponto registrado com sucesso.', 'data' => $this->normalize($service->punch($request->user(), $request, 'mobile', $validated['expected_type']))],
                'status' => 201,
            ],
        );

        return response()->json($result['body'], $result['status']);
    }

    private function normalize(array $data): array
    {
        return [
            'server_time' => $data['serverTime'] ?? null,
            'timezone' => $data['timezone'] ?? config('app.business_timezone'),
            'next_type' => $data['nextType'] ?? null,
            'registered_type' => $data['registeredType'] ?? null,
            'registered_at' => $data['registeredAt'] ?? null,
            'status' => $data['status'] ?? null,
            'reason' => $data['reason'] ?? null,
            'entries' => $data['entries'] ?? [],
            'pending_adjustments' => $data['pendingAdjustments'] ?? [],
        ];
    }
}
