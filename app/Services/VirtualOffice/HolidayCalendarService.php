<?php

namespace App\Services\VirtualOffice;

use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;

class HolidayCalendarService
{
    public function forDate(User $user, CarbonImmutable $date): ?Holiday
    {
        $company = $user->employeeProfile?->company;

        return Holiday::query()
            ->whereDate('holiday_date', $date)
            ->where('active', true)
            ->where(function ($query) use ($company): void {
                $query->whereNull('company_id');
                if ($company) {
                    $query->orWhere('company_id', $company->id);
                }
            })
            ->where(fn ($query) => $query->whereNull('state')->when($company?->state, fn ($stateQuery, $state) => $stateQuery->orWhere('state', $state)))
            ->where(fn ($query) => $query->whereNull('city')->when($company?->city, fn ($cityQuery, $city) => $cityQuery->orWhere('city', $city)))
            ->first();
    }

    public function coversWholeDay(Holiday $holiday): bool
    {
        if (! $holiday->starts_at && ! $holiday->ends_at) {
            return true;
        }

        return $holiday->starts_at !== null
            && $holiday->ends_at !== null
            && substr($holiday->starts_at, 0, 5) === '00:00'
            && substr($holiday->ends_at, 0, 5) === '23:59';
    }

    public function coversTime(Holiday $holiday, CarbonImmutable $dateTime): bool
    {
        if ($this->coversWholeDay($holiday)) {
            return true;
        }

        $time = $dateTime->format('H:i:s');

        return $time >= $holiday->starts_at && $time <= $holiday->ends_at;
    }
}
