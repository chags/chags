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

            $adjustment->timeEntries()->firstOrCreate(
                ['type' => $requestedEntry['type']],
                [
                    'user_id' => $adjustment->user_id,
                    'recorded_at' => $recordedAt,
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
