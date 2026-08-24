<?php

namespace App\Http\Controllers\VirtualOffice;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimePunchController extends Controller
{
    private const TYPES = ['clock_in', 'break_start', 'break_end', 'clock_out'];

    public function show(Request $request): JsonResponse
    {
        $this->ensureCanPunch($request);

        return response()->json($this->status($request));
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanPunch($request);
        $status = $this->status($request);
        abort_if($status['nextType'] === null, 422, 'Todos os registros de hoje já foram realizados.');

        $entry = TimeEntry::query()->create([
            'user_id' => $request->user()->id,
            'recorded_at' => now(),
            'type' => $status['nextType'],
            'source' => 'web',
            'ip_address' => $request->ip(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Ponto registrado com sucesso!',
            'registeredType' => $entry->type,
            'registeredAt' => $entry->recorded_at->format('H:i:s'),
            ...$this->status($request),
        ], 201);
    }

    private function ensureCanPunch(Request $request): void
    {
        abort_unless($request->user()->tracks_time, 403, 'Seu usuário não está configurado para registrar ponto.');
    }

    private function status(Request $request): array
    {
        $entries = $request->user()->timeEntries()
            ->whereBetween('recorded_at', [now()->startOfDay(), now()->endOfDay()])
            ->orderBy('recorded_at')
            ->get();
        $registeredTypes = $entries->pluck('type');

        return [
            'nextType' => collect(self::TYPES)->first(fn (string $type) => ! $registeredTypes->contains($type)),
            'entries' => $entries->map(fn (TimeEntry $entry) => [
                'type' => $entry->type,
                'time' => $entry->recorded_at->format('H:i'),
            ])->values()->all(),
            'pendingAdjustments' => $request->user()->timeAdjustmentRequests()
                ->where('status', 'pending')
                ->latest()
                ->get()
                ->flatMap(fn ($adjustment) => collect($adjustment->requested_entries)->map(fn (array $entry) => [
                    'id' => $adjustment->id,
                    'date' => $adjustment->work_date->format('Y-m-d'),
                    'type' => $entry['type'],
                    'time' => $entry['time'],
                ]))->values()->all(),
        ];
    }
}
