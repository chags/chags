<?php

namespace App\Http\Controllers\VirtualOffice;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Services\VirtualOffice\TimePunchDecisionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

    public function store(Request $request, TimePunchDecisionService $decisionService): JsonResponse
    {
        $this->ensureCanPunch($request);
        $status = $this->status($request);
        abort_if($status['nextType'] === null, 422, 'Todos os registros de hoje já foram realizados.');

        $recordedAt = CarbonImmutable::now();
        $decision = $decisionService->decide($request->user(), $status['nextType'], $recordedAt, 'web');
        $entry = TimeEntry::query()->create([
            'user_id' => $request->user()->id,
            'recorded_at' => $recordedAt,
            'type' => $status['nextType'],
            'source' => 'web',
            ...$decision,
            'ip_address' => $request->ip(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Ponto registrado com sucesso!',
            'registeredType' => $entry->type,
            'registeredAt' => $this->localTime($entry->recorded_at)->format('H:i:s'),
            'status' => $entry->status,
            'reason' => $entry->reason,
            ...$this->status($request),
        ], 201);
    }

    private function ensureCanPunch(Request $request): void
    {
        abort_unless($request->user()->tracks_time, 403, 'Seu usuário não está configurado para registrar ponto.');
    }

    private function status(Request $request): array
    {
        $localNow = CarbonImmutable::now(config('app.business_timezone'));
        $entries = $request->user()->timeEntries()
            ->whereBetween('recorded_at', [
                $localNow->startOfDay()->utc(),
                $localNow->endOfDay()->utc(),
            ])
            ->orderBy('recorded_at')
            ->get();
        $registeredTypes = $entries->whereNotIn('status', ['cancelled'])->pluck('type');

        return [
            'nextType' => collect(self::TYPES)->first(fn (string $type) => ! $registeredTypes->contains($type)),
            'entries' => $entries->map(fn (TimeEntry $entry) => [
                'type' => $entry->type,
                'time' => $this->localTime($entry->recorded_at)->format('H:i'),
                'status' => $entry->status,
                'reason' => $entry->reason,
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

    private function localTime(CarbonInterface $dateTime): CarbonInterface
    {
        return $dateTime->setTimezone(config('app.business_timezone'));
    }
}
