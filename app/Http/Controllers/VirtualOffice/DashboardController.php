<?php

namespace App\Http\Controllers\VirtualOffice;

use App\Http\Controllers\Controller;
use App\Services\VirtualOffice\TimeCardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, TimeCardService $timeCardService): Response
    {
        $user = $request->user()->load(['employeeProfile.department:id,name', 'employeeProfile.position:id,title,level']);
        $employee = $user->employeeProfile;
        $today = CarbonImmutable::today();
        $timeCard = $user->tracks_time ? $timeCardService->forMonth($user, $today) : null;
        $todaySummary = $timeCard
            ? collect($timeCard['days'])->firstWhere('date', $today->toDateString())
            : null;
        $vacation = $user->vacationPeriods()
            ->whereIn('status', ['accruing', 'available', 'scheduled'])
            ->orderBy('accrual_start')
            ->first();

        return Inertia::render('virtual-office/dashboard', [
            'employee' => [
                'name' => $user->name,
                'employeeNumber' => $employee?->employee_number,
                'department' => $employee?->department?->name,
                'position' => $employee?->position?->display_name,
            ],
            'today' => $todaySummary,
            'month' => [
                'workedMinutes' => $timeCard['workedMinutes'] ?? 0,
                'expectedMinutes' => $timeCard['expectedMinutes'] ?? 0,
                'balanceMinutes' => $timeCard['monthBalanceMinutes'] ?? 0,
            ],
            'hourBankBalanceMinutes' => $timeCard['currentBalanceMinutes'] ?? 0,
            'vacation' => $vacation ? [
                'accrualStart' => $vacation->accrual_start->toDateString(),
                'accrualEnd' => $vacation->accrual_end->toDateString(),
                'availableDays' => $vacation->available_days,
                'scheduledStart' => $vacation->scheduled_start?->toDateString(),
                'scheduledEnd' => $vacation->scheduled_end?->toDateString(),
                'status' => $vacation->status,
            ] : null,
            'pendingAdjustments' => $user->timeAdjustmentRequests()->where('status', 'pending')->count(),
            'tracksTime' => $user->tracks_time,
            'canSubmitAbsenceDocument' => $user->can('medical-certificates.submit'),
        ]);
    }
}
