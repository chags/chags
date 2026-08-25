<?php

namespace App\Services\VirtualOffice;

use App\Models\User;
use Carbon\CarbonImmutable;

class TimePunchDecisionService
{
    public function __construct(
        private readonly WorkScheduleResolver $scheduleResolver,
        private readonly HolidayCalendarService $holidayCalendar,
    ) {}

    /** @return array{status: string, reason: ?string} */
    public function decide(User $user, string $type, CarbonImmutable $recordedAt, string $source): array
    {
        if ($source === 'manual') {
            return ['status' => 'pending', 'reason' => 'manual_entry'];
        }

        $localRecordedAt = $recordedAt->setTimezone(config('app.business_timezone'));
        $holiday = $this->holidayCalendar->forDate($user, $localRecordedAt->startOfDay());
        if ($holiday && $this->holidayCalendar->coversTime($holiday, $localRecordedAt)) {
            return ['status' => 'pending', 'reason' => 'holiday'];
        }

        $schedule = $this->scheduleResolver->forDate($user, $localRecordedAt->startOfDay());
        if (! $schedule || $schedule['day_type'] === 'hour_bank_leave') {
            return [
                'status' => 'pending',
                'reason' => $schedule && $schedule['day_type'] === 'hour_bank_leave' ? 'hour_bank_leave' : 'day_off',
            ];
        }

        $scheduleKey = match ($type) {
            'clock_in' => 'start_time',
            'break_start' => 'break_start_time',
            'break_end' => 'break_end_time',
            'clock_out' => 'end_time',
            default => null,
        };
        $expectedTime = $scheduleKey ? ($schedule[$scheduleKey] ?? null) : null;

        if (! $expectedTime) {
            return ['status' => 'pending', 'reason' => 'schedule_not_configured'];
        }

        $expectedAt = CarbonImmutable::parse(
            $localRecordedAt->format('Y-m-d').' '.$expectedTime,
            config('app.business_timezone'),
        );
        $insideWindow = abs($expectedAt->diffInMinutes($localRecordedAt, false)) <= (int) ($schedule['window_minutes'] ?? 10);

        return $insideWindow
            ? ['status' => 'approved', 'reason' => null]
            : ['status' => 'cancelled', 'reason' => 'outside_window'];
    }
}
