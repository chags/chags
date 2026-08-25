<?php

namespace App\Services\VirtualOffice;

use App\Models\User;
use App\Models\WorkScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkScheduleExceptionService
{
    public function __construct(private readonly WorkScheduleResolver $scheduleResolver) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, User $manager, array $data): WorkScheduleException
    {
        return DB::transaction(function () use ($user, $manager, $data): WorkScheduleException {
            $date = CarbonImmutable::parse($data['work_date'])->startOfDay();
            $exists = $user->workScheduleExceptions()
                ->whereDate('work_date', $date)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'work_date' => 'Já existe uma exceção ativa para este colaborador nesta data.',
                ]);
            }

            $minutes = $data['type'] === 'hour_bank_leave'
                ? $this->leaveMinutes($user, $date)
                : $this->customScheduleMinutes($data);

            if ($data['type'] === 'hour_bank_leave') {
                $balance = (int) $user->hourBankTransactions()->lockForUpdate()->sum('minutes');
                if ($balance < $minutes) {
                    throw ValidationException::withMessages([
                        'user_id' => "Saldo insuficiente. Disponível: {$this->formatMinutes($balance)}.",
                    ]);
                }
            }

            $exception = $user->workScheduleExceptions()->create([
                'work_date' => $date,
                'type' => $data['type'],
                'start_time' => $data['type'] === 'custom_schedule' ? $data['start_time'] : null,
                'break_start_time' => $data['type'] === 'custom_schedule' ? ($data['break_start_time'] ?? null) : null,
                'break_end_time' => $data['type'] === 'custom_schedule' ? ($data['break_end_time'] ?? null) : null,
                'end_time' => $data['type'] === 'custom_schedule' ? $data['end_time'] : null,
                'expected_minutes' => $data['type'] === 'hour_bank_leave' ? 0 : $minutes,
                'reason' => $data['reason'],
                'status' => 'approved',
                'created_by' => $manager->id,
            ]);

            if ($data['type'] === 'hour_bank_leave') {
                $exception->hourBankTransactions()->create([
                    'user_id' => $user->id,
                    'work_date' => $date,
                    'minutes' => -$minutes,
                    'type' => 'leave',
                    'description' => 'Folga compensatória aprovada pelo gestor.',
                    'created_by' => $manager->id,
                ]);
            }

            return $exception;
        });
    }

    private function leaveMinutes(User $user, CarbonImmutable $date): int
    {
        $schedule = $this->scheduleResolver->forDate($user, $date, false);
        $minutes = (int) ($schedule['daily_minutes'] ?? 0);

        if ($minutes <= 0) {
            throw ValidationException::withMessages([
                'work_date' => 'Não existe jornada de trabalho prevista para esta data.',
            ]);
        }

        return $minutes;
    }

    /** @param array<string, mixed> $data */
    private function customScheduleMinutes(array $data): int
    {
        $start = CarbonImmutable::createFromFormat('H:i', $data['start_time']);
        $end = CarbonImmutable::createFromFormat('H:i', $data['end_time']);
        $minutes = $start->diffInMinutes($end, false);

        if (! empty($data['break_start_time']) && ! empty($data['break_end_time'])) {
            $breakStart = CarbonImmutable::createFromFormat('H:i', $data['break_start_time']);
            $breakEnd = CarbonImmutable::createFromFormat('H:i', $data['break_end_time']);
            $minutes -= $breakStart->diffInMinutes($breakEnd, false);
        }

        if ($minutes <= 0) {
            throw ValidationException::withMessages([
                'end_time' => 'A jornada excepcional deve possuir duração positiva.',
            ]);
        }

        return $minutes;
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv(max(0, $minutes), 60), max(0, $minutes) % 60);
    }
}
