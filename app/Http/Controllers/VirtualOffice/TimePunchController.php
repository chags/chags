<?php

namespace App\Http\Controllers\VirtualOffice;

use App\Http\Controllers\Controller;
use App\Services\VirtualOffice\TimePunchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimePunchController extends Controller
{
    public function show(Request $request, TimePunchService $service): JsonResponse
    {
        $this->ensureCanPunch($request);

        return response()->json($service->status($request->user()));
    }

    public function store(Request $request, TimePunchService $service): JsonResponse
    {
        $this->ensureCanPunch($request);

        return response()->json($service->punch($request->user(), $request, 'web'), 201);
    }

    private function ensureCanPunch(Request $request): void
    {
        abort_unless($request->user()->tracks_time, 403, 'Seu usuário não está configurado para registrar ponto.');
    }
}
