<?php

namespace App\Services\VirtualOffice;

use App\Models\User;
use Carbon\CarbonImmutable;

class WorkScheduleResolver
{
    /** @return array<string, mixed>|null */
    public function forDate(User $user, CarbonImmutable $date, bool $includeException = true): ?array
    {
        if ($includeException) {
            $exception = $user->workScheduleExceptions()
                ->whereDate('work_date', $date)
                ->where('status', 'approved')
                ->latest('id')
                ->first();

            if ($exception) {
                return [
                    'daily_minutes' => $exception->expected_minutes,
                    'start_time' => $exception->start_time,
                    'break_start_time' => $exception->break_start_time,
                    'break_end_time' => $exception->break_end_time,
                    'end_time' => $exception->end_time,
                    'day_type' => $exception->type,
                    'exception_id' => $exception->id,
                    'window_minutes' => 10,
                ];
            }
        }

        $assignment = $user->workScheduleAssignments()
            ->where('active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->with('group.days')
            ->latest('valid_from')
            ->first();

        if ($assignment?->group?->active) {
            $group = $assignment->group;
            $dayIndex = $date->isoWeekday();

            if (in_array($group->schedule_type, ['12x36', 'custom'], true) && $group->cycle_start_date) {
                $cycleLength = max(1, $group->days->count());
                $dayIndex = (($group->cycle_start_date->diffInDays($date, false) % $cycleLength) + $cycleLength) % $cycleLength + 1;
            }

            $day = $group->days->firstWhere('day_index', $dayIndex);
            if ($day?->is_workday && $day->start_time && $day->end_time) {
                return [
                    'daily_minutes' => $day->expected_minutes,
                    'start_time' => $day->start_time,
                    'break_start_time' => $day->break_start_time,
                    'break_end_time' => $day->break_end_time,
                    'end_time' => $day->end_time,
                    'day_type' => 'workday',
                    'window_minutes' => $group->operational_window_minutes,
                ];
            }
        }

        $schedule = $user->workSchedules()
            ->where('active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->latest('valid_from')
            ->get()
            ->first(fn ($item) => in_array($date->isoWeekday(), $item->weekdays, true));

        if (! $schedule) {
            return null;
        }

        return [
            'daily_minutes' => $schedule->daily_minutes,
            'start_time' => $schedule->start_time,
            'break_start_time' => $schedule->break_start_time,
            'break_end_time' => $schedule->break_end_time,
            'end_time' => $schedule->end_time,
            'day_type' => 'workday',
            'window_minutes' => 10,
        ];
    }
}
