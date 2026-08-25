<?php

namespace App\Services\VirtualOffice;

use App\Models\AbsenceJustification;
use App\Models\EmployeeWorkSchedule;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class TimeCardService
{
    public function __construct(private readonly HolidayCalendarService $holidayCalendar) {}

    /** @return array{month: string, workedMinutes: int, expectedMinutes: int, monthBalanceMinutes: int, currentBalanceMinutes: int, days: list<array<string, mixed>>} */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $start = CarbonImmutable::createFromFormat(
            '!Y-m',
            $month->format('Y-m'),
            config('app.business_timezone'),
        )->startOfMonth();
        $end = $start->endOfMonth();
        $entries = $user->timeEntries()
            ->whereBetween('recorded_at', [
                $start->startOfDay()->utc(),
                $end->endOfDay()->utc(),
            ])
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(fn (TimeEntry $entry) => $this->localTime($entry)->toDateString());
        $schedules = $user->workSchedules()
            ->where('active', true)
            ->whereDate('valid_from', '<=', $end)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $start))
            ->orderByDesc('valid_from')
            ->get();
        $assignments = $user->workScheduleAssignments()
            ->where('active', true)
            ->whereDate('valid_from', '<=', $end)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $start))
            ->with('group.days')
            ->orderByDesc('valid_from')
            ->get();
        $transactions = $user->hourBankTransactions()
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->groupBy(fn ($transaction) => $transaction->work_date->toDateString());
        $runningBalance = (int) $user->hourBankTransactions()
            ->whereDate('work_date', '<', $start)
            ->sum('minutes');
        $adjustments = $user->timeAdjustmentRequests()
            ->whereBetween('work_date', [$start, $end])
            ->latest()
            ->get()
            ->groupBy(fn ($request) => $request->work_date->toDateString());
        $exceptions = $user->workScheduleExceptions()
            ->whereBetween('work_date', [$start, $end])
            ->where('status', 'approved')
            ->latest('id')
            ->get()
            ->groupBy(fn ($exception) => $exception->work_date->toDateString());
        $absences = $user->absenceJustifications()
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->latest()
            ->get();

        $days = [];
        $workedMinutes = 0;
        $expectedMinutes = 0;
        $monthBalanceMinutes = 0;

        foreach (CarbonPeriod::create($start, $end) as $periodDate) {
            $date = CarbonImmutable::instance($periodDate);
            $dateKey = $date->toDateString();
            $exception = $exceptions->get($dateKey)?->first();
            $holiday = $this->holidayCalendar->forDate($user, $date);
            $isFullHoliday = $holiday && $this->holidayCalendar->coversWholeDay($holiday);
            $schedule = $isFullHoliday
                ? null
                : ($exception
                ? ($exception->type === 'custom_schedule' ? [
                    'daily_minutes' => $exception->expected_minutes,
                    'start_time' => $exception->start_time,
                    'break_start_time' => $exception->break_start_time,
                    'break_end_time' => $exception->break_end_time,
                    'end_time' => $exception->end_time,
                ] : null)
                : ($this->groupScheduleForDate($assignments, $date) ?? $this->scheduleForDate($schedules, $date)));
            $dayEntries = $entries->get($dateKey, collect());
            $dayAdjustments = $adjustments->get($dateKey, collect());
            $absence = $absences->first(fn ($item) => $item->starts_on->toDateString() <= $dateKey
                && $item->ends_on->toDateString() >= $dateKey);
            $excusedMinutes = $absence?->status === 'approved'
                ? $this->excusedMinutes($absence, (int) ($schedule['daily_minutes'] ?? 0))
                : 0;
            $expectedForDay = max(0, (int) ($schedule['daily_minutes'] ?? 0) - $excusedMinutes);
            $dayWorked = $this->workedMinutes($dayEntries);
            $dayBalance = (int) $transactions->get($dateKey, collect())->sum('minutes');
            $runningBalance += $dayBalance;
            $workedMinutes += $dayWorked;
            $expectedMinutes += $expectedForDay;
            $monthBalanceMinutes += $dayBalance;

            $days[] = [
                'date' => $dateKey,
                'weekday' => $date->isoWeekday(),
                'expectedMinutes' => $expectedForDay,
                'excusedMinutes' => $excusedMinutes,
                'schedule' => $schedule ? [
                    'start' => substr($schedule['start_time'], 0, 5),
                    'breakStart' => $schedule['break_start_time'] ? substr($schedule['break_start_time'], 0, 5) : null,
                    'breakEnd' => $schedule['break_end_time'] ? substr($schedule['break_end_time'], 0, 5) : null,
                    'end' => substr($schedule['end_time'], 0, 5),
                ] : null,
                'entries' => $dayEntries->map(fn (TimeEntry $entry) => [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'time' => $this->localTime($entry)->format('H:i'),
                    'status' => $entry->status,
                    'reason' => $entry->reason,
                    'source' => $entry->source,
                ])->values()->all(),
                'pendingEntries' => $dayAdjustments
                    ->where('status', 'pending')
                    ->flatMap(fn ($adjustment) => collect($adjustment->requested_entries)->map(fn (array $entry) => [
                        'requestId' => $adjustment->id,
                        'type' => $entry['type'],
                        'time' => $entry['time'],
                    ]))->values()->all(),
                'workedMinutes' => $dayWorked,
                'balanceMinutes' => $dayBalance,
                'accumulatedBalanceMinutes' => $runningBalance,
                'occurrence' => $isFullHoliday
                    ? 'holiday'
                    : ($absence?->status === 'approved'
                    ? 'medical_leave'
                    : ($absence?->status === 'pending'
                    ? 'medical_pending'
                    : ($exception?->type === 'hour_bank_leave'
                    ? 'hour_bank_leave'
                    : $this->occurrence($schedule, $dayEntries, $date)))),
                'dayType' => $isFullHoliday ? 'holiday' : ($exception?->type ?? ($schedule ? 'workday' : 'day_off')),
                'holiday' => $holiday ? ['name' => $holiday->name, 'partial' => ! $isFullHoliday] : null,
                'absence' => $absence ? ['status' => $absence->status, 'type' => $absence->type] : null,
                'adjustmentStatus' => $dayAdjustments->contains('status', 'pending')
                    ? 'pending'
                    : $dayAdjustments->first()?->status,
            ];
        }

        return [
            'month' => $start->format('Y-m'),
            'workedMinutes' => $workedMinutes,
            'expectedMinutes' => $expectedMinutes,
            'monthBalanceMinutes' => $monthBalanceMinutes,
            'currentBalanceMinutes' => (int) $user->hourBankTransactions()->whereDate('work_date', '<=', $end)->sum('minutes'),
            'days' => $days,
        ];
    }

    /** @param Collection<int, EmployeeWorkSchedule> $schedules */
    private function scheduleForDate(Collection $schedules, CarbonImmutable $date): ?array
    {
        $schedule = $schedules->first(fn (EmployeeWorkSchedule $schedule) => $schedule->valid_from->lte($date)
            && (! $schedule->valid_until || $schedule->valid_until->gte($date))
            && in_array($date->isoWeekday(), $schedule->weekdays, true));

        return $schedule ? ['daily_minutes' => $schedule->daily_minutes, 'start_time' => $schedule->start_time, 'break_start_time' => $schedule->break_start_time, 'break_end_time' => $schedule->break_end_time, 'end_time' => $schedule->end_time] : null;
    }

    private function groupScheduleForDate(Collection $assignments, CarbonImmutable $date): ?array
    {
        $assignment = $assignments->first(fn ($item) => $item->valid_from->lte($date) && (! $item->valid_until || $item->valid_until->gte($date)));
        if (! $assignment || ! $assignment->group?->active) {
            return null;
        }
        $group = $assignment->group;
        $dayIndex = $date->isoWeekday();
        if (in_array($group->schedule_type, ['12x36', 'custom'], true) && $group->cycle_start_date) {
            $cycleLength = max(1, $group->days->count());
            $dayIndex = (($group->cycle_start_date->diffInDays($date, false) % $cycleLength) + $cycleLength) % $cycleLength + 1;
        }
        $day = $group->days->firstWhere('day_index', $dayIndex);
        if (! $day?->is_workday || ! $day->start_time || ! $day->end_time) {
            return null;
        }

        return ['daily_minutes' => $day->expected_minutes, 'start_time' => $day->start_time, 'break_start_time' => $day->break_start_time, 'break_end_time' => $day->break_end_time, 'end_time' => $day->end_time];
    }

    /** @param Collection<int, TimeEntry> $entries */
    private function workedMinutes(Collection $entries): int
    {
        $entries = $entries->where('status', 'approved');
        $byType = $entries->keyBy('type');
        $clockIn = $byType->get('clock_in')?->recorded_at;
        $clockOut = $byType->get('clock_out')?->recorded_at;

        if (! $clockIn || ! $clockOut || $clockOut->lte($clockIn)) {
            return 0;
        }

        $minutes = $clockIn->diffInMinutes($clockOut);
        $breakStart = $byType->get('break_start')?->recorded_at;
        $breakEnd = $byType->get('break_end')?->recorded_at;

        if ($breakStart && $breakEnd && $breakEnd->gt($breakStart)) {
            $minutes -= $breakStart->diffInMinutes($breakEnd);
        }

        return max(0, $minutes);
    }

    private function localTime(TimeEntry $entry): CarbonImmutable
    {
        return CarbonImmutable::instance($entry->recorded_at)
            ->setTimezone(config('app.business_timezone'));
    }

    /** @param Collection<int, TimeEntry> $entries */
    private function occurrence(?array $schedule, Collection $entries, CarbonImmutable $date): ?string
    {
        if (! $schedule) {
            return null;
        }

        if ($entries->isEmpty()) {
            return $date->isPast() && ! $date->isToday() ? 'missing' : null;
        }

        return $entries->where('status', 'approved')->pluck('type')->unique()->count() < 4 ? 'incomplete' : null;
    }

    private function excusedMinutes(AbsenceJustification $absence, int $scheduledMinutes): int
    {
        if (! $absence->starts_at || ! $absence->ends_at) {
            return $scheduledMinutes;
        }

        $start = CarbonImmutable::createFromFormat('H:i:s', $absence->starts_at);
        $end = CarbonImmutable::createFromFormat('H:i:s', $absence->ends_at);

        return min($scheduledMinutes, max(0, (int) $start->diffInMinutes($end, false)));
    }
}
