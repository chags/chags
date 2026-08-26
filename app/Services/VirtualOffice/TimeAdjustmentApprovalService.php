<?php

namespace App\Services\VirtualOffice;

use App\Models\TimeAdjustmentRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeAdjustmentApprovalService
{
    public function decide(TimeAdjustmentRequest $adjustment, User $reviewer, string $decision, ?string $notes): TimeAdjustmentRequest
    {
        return DB::transaction(function () use ($adjustment, $reviewer, $decision, $notes): TimeAdjustmentRequest {
            $adjustment = TimeAdjustmentRequest::query()->lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'Esta solicitação já foi analisada.',
                ]);
            }

            $status = $decision === 'approve' ? 'approved' : 'cancelled';

            if ($status === 'approved') {
                $this->validateOvertime($adjustment);
            }

            $adjustment->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            if ($status === 'approved') {
                $this->createApprovedEntries($adjustment);
                $this->creditApprovedOvertime($adjustment, $reviewer);
            }

            return $adjustment->refresh();
        });
    }

    private function validateOvertime(TimeAdjustmentRequest $adjustment): void
    {
        $requested = collect($adjustment->requested_entries)->keyBy('type');
        if (! $requested->hasAny(['overtime_start', 'overtime_end'])) {
            return;
        }

        $localDate = CarbonImmutable::parse($adjustment->work_date, config('app.business_timezone'));
        $approvedEntries = $adjustment->user->timeEntries()
            ->whereDate('work_date', $adjustment->work_date)
            ->where('status', 'approved')
            ->whereIn('type', ['clock_out', 'overtime_start', 'overtime_end'])
            ->get()
            ->keyBy('type');
        $clockOut = $approvedEntries->get('clock_out')?->recorded_at;

        if (! $clockOut) {
            throw ValidationException::withMessages([
                'requested_entries' => 'A hora extra só pode ser aprovada depois da saída normal.',
            ]);
        }

        $parseRequested = fn (string $type): ?CarbonImmutable => $requested->has($type)
            ? CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                $localDate->format('Y-m-d').' '.$requested->get($type)['time'],
                config('app.business_timezone'),
            )->utc()
            : null;
        $start = $parseRequested('overtime_start') ?? $approvedEntries->get('overtime_start')?->recorded_at;
        $end = $parseRequested('overtime_end') ?? $approvedEntries->get('overtime_end')?->recorded_at;

        if ($start && $start->lte($clockOut)) {
            throw ValidationException::withMessages([
                'requested_entries' => 'A hora extra deve começar depois da saída normal.',
            ]);
        }
        if ($end && ! $start) {
            throw ValidationException::withMessages([
                'requested_entries' => 'Informe ou aprove o início da hora extra antes do término.',
            ]);
        }
        if ($start && $end && ($end->lte($start) || $start->diffInMinutes($end) > 120)) {
            throw ValidationException::withMessages([
                'requested_entries' => 'A hora extra deve terminar depois do início e não pode ultrapassar 2 horas.',
            ]);
        }
    }

    private function creditApprovedOvertime(TimeAdjustmentRequest $adjustment, User $reviewer): void
    {
        $containsOvertime = collect($adjustment->requested_entries)
            ->pluck('type')
            ->intersect(['overtime_start', 'overtime_end'])
            ->isNotEmpty();

        if (! $containsOvertime) {
            return;
        }

        $localDate = CarbonImmutable::parse($adjustment->work_date, config('app.business_timezone'));
        $entries = $adjustment->user->timeEntries()
            ->whereBetween('recorded_at', [
                $localDate->startOfDay()->utc(),
                $localDate->endOfDay()->utc(),
            ])
            ->where('status', 'approved')
            ->whereIn('type', ['overtime_start', 'overtime_end'])
            ->orderBy('recorded_at')
            ->get()
            ->keyBy('type');
        $start = $entries->get('overtime_start')?->recorded_at;
        $end = $entries->get('overtime_end')?->recorded_at;

        if (! $start || ! $end || $end->lte($start)) {
            return;
        }

        $alreadyCredited = $adjustment->user->hourBankTransactions()
            ->whereDate('work_date', $adjustment->work_date)
            ->where('type', 'overtime')
            ->exists();

        if ($alreadyCredited) {
            return;
        }

        $adjustment->hourBankTransactions()->create([
            'user_id' => $adjustment->user_id,
            'work_date' => $adjustment->work_date,
            'minutes' => (int) $start->diffInMinutes($end),
            'type' => 'overtime',
            'description' => 'Período de hora extra aprovado pelo gestor.',
            'created_by' => $reviewer->id,
        ]);
    }

    private function createApprovedEntries(TimeAdjustmentRequest $adjustment): void
    {
        foreach ($adjustment->requested_entries as $requestedEntry) {
            $recordedAt = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                $adjustment->work_date->format('Y-m-d').' '.$requestedEntry['time'],
                config('app.business_timezone'),
            )->utc();

            $alreadyExists = $adjustment->user->timeEntries()
                ->whereDate('work_date', $adjustment->work_date)
                ->where('type', $requestedEntry['type'])
                ->where('status', '<>', 'cancelled')
                ->exists();

            if ($alreadyExists) {
                throw ValidationException::withMessages([
                    'requested_entries' => "Já existe uma batida ativa do tipo {$requestedEntry['type']} nesta data.",
                ]);
            }

            $adjustment->timeEntries()->firstOrCreate(
                ['type' => $requestedEntry['type']],
                [
                    'user_id' => $adjustment->user_id,
                    'recorded_at' => $recordedAt,
                    'work_date' => $adjustment->work_date,
                    'source' => 'manual',
                    'status' => 'approved',
                    'reason' => 'manual_entry',
                    'notes' => $adjustment->reason,
                    'created_by' => $adjustment->user_id,
                ],
            );
        }
    }
}
