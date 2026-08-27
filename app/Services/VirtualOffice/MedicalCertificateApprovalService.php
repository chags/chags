<?php

namespace App\Services\VirtualOffice;

use App\Models\AbsenceJustification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalCertificateApprovalService
{
    public function __construct(private readonly WorkScheduleResolver $scheduleResolver) {}

    public function approve(AbsenceJustification $justification, User $reviewer, ?string $notes): AbsenceJustification
    {
        return DB::transaction(function () use ($justification, $reviewer, $notes): AbsenceJustification {
            $justification = AbsenceJustification::query()->lockForUpdate()->findOrFail($justification->id);
            $this->ensurePending($justification);

            if ($justification->type === 'medical_certificate') {
                $this->createExcusedEntries($justification, $reviewer);
            }

            $justification->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $justification;
        });
    }

    public function reject(AbsenceJustification $justification, User $reviewer, string $notes): AbsenceJustification
    {
        return DB::transaction(function () use ($justification, $reviewer, $notes): AbsenceJustification {
            $justification = AbsenceJustification::query()->lockForUpdate()->findOrFail($justification->id);
            $this->ensurePending($justification);
            $justification->update([
                'status' => 'cancelled',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $justification;
        });
    }

    public function syncApproved(AbsenceJustification $justification): int
    {
        return DB::transaction(function () use ($justification): int {
            $justification = AbsenceJustification::query()->lockForUpdate()->findOrFail($justification->id);

            if ($justification->status !== 'approved' || $justification->type !== 'medical_certificate') {
                return 0;
            }

            return $this->createExcusedEntries($justification, $justification->reviewer);
        });
    }

    private function createExcusedEntries(AbsenceJustification $justification, ?User $reviewer): int
    {
        $timezone = config('app.business_timezone');
        $created = 0;

        foreach (CarbonPeriod::create($justification->starts_on, $justification->ends_on) as $periodDate) {
            $date = CarbonImmutable::parse($periodDate->format('Y-m-d'), $timezone)->startOfDay();
            $schedule = $this->scheduleResolver->forDate($justification->user, $date);

            if (! $schedule || ($schedule['day_type'] ?? null) !== 'workday') {
                continue;
            }

            $types = array_filter([
                'clock_in' => $schedule['start_time'] ?? null,
                'break_start' => $schedule['break_start_time'] ?? null,
                'break_end' => $schedule['break_end_time'] ?? null,
                'clock_out' => $schedule['end_time'] ?? null,
            ]);
            $existingEntries = $justification->user->timeEntries()
                ->whereDate('work_date', $date)
                ->whereIn('type', array_keys($types))
                ->where('status', '<>', 'cancelled')
                ->get()
                ->keyBy('type');

            foreach ($types as $type => $time) {
                $existing = $existingEntries->get($type);
                if ($existing) {
                    if ($existing->absence_justification_id === $justification->id
                        && $existing->source === 'medical_certificate') {
                        continue;
                    }

                    throw ValidationException::withMessages([
                        'decision' => 'Existem batidas ativas em '.$date->format('d/m/Y').'. Analise o cartão antes de aprovar o atestado.',
                    ]);
                }

                $recordedAt = CarbonImmutable::parse($date->toDateString().' '.$time, $timezone)->utc();
                $justification->timeEntries()->create([
                    'user_id' => $justification->user_id,
                    'work_date' => $date->toDateString(),
                    'recorded_at' => $recordedAt,
                    'type' => $type,
                    'source' => 'medical_certificate',
                    'status' => 'approved',
                    'reason' => 'medical_certificate',
                    'notes' => 'Jornada abonada pelo atestado #'.$justification->id,
                    'created_by' => $reviewer?->id,
                    'reviewed_by' => $reviewer?->id,
                    'reviewed_at' => now(),
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function ensurePending(AbsenceJustification $justification): void
    {
        if ($justification->status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'Este documento já foi analisado.']);
        }
    }
}
