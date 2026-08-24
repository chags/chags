<?php

namespace App\Http\Controllers\VirtualOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\VirtualOffice\StoreTimeAdjustmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TimeAdjustmentRequestController extends Controller
{
    public function store(StoreTimeAdjustmentRequest $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->tracks_time, 403, 'Seu usuário não está configurado para registrar ponto.');
        $requestedTypes = collect($request->input('requested_entries'))->pluck('type');
        $hasDuplicateRequest = $request->user()->timeAdjustmentRequests()
            ->whereDate('work_date', $request->date('work_date'))
            ->where('status', 'pending')
            ->get()
            ->contains(fn ($adjustment) => collect($adjustment->requested_entries)->pluck('type')->intersect($requestedTypes)->isNotEmpty());
        abort_if($hasDuplicateRequest, 422, 'Já existe uma solicitação pendente para este tipo de batida nesta data.');

        $request->user()->timeAdjustmentRequests()->create([
            ...$request->validated(),
            'status' => 'pending',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Ajuste enviado para aprovação do gestor.', 'status' => 'pending'], 201);
        }

        return back()->with('success', 'Solicitação de ajuste enviada com sucesso.');
    }
}
