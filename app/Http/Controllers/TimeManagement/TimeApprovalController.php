<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\ReviewTimeAdjustmentRequest;
use App\Models\AbsenceJustification;
use App\Models\TimeAdjustmentRequest;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkScheduleException;
use App\Services\VirtualOffice\TimeAdjustmentApprovalService;
use App\Services\VirtualOffice\TimeCardService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $reviewer = $request->user();
        $canApproveTime = $reviewer->can('time-records.approve');
        $adjustments = $this->visibleAdjustments($request->user())
            ->when(! $canApproveTime, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->with(['user.employeeProfile.department', 'reviewer'])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TimeAdjustmentRequest $adjustment) => [
                'id' => $adjustment->id,
                'employee' => [
                    'id' => $adjustment->user->id,
                    'name' => $adjustment->user->name,
                    'department' => $adjustment->user->employeeProfile?->department?->name,
                ],
                'workDate' => $adjustment->work_date->format('Y-m-d'),
                'entries' => $adjustment->requested_entries,
                'reason' => $adjustment->reason,
                'status' => $adjustment->status,
                'reviewNotes' => $adjustment->review_notes,
                'reviewer' => $adjustment->reviewer?->name,
                'reviewedAt' => $adjustment->reviewed_at?->toIso8601String(),
                'createdAt' => $adjustment->created_at?->toIso8601String(),
            ]);

        $employees = User::query()
            ->when(! $canApproveTime, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('tracks_time', true)
            ->when(! $this->canReviewAll($reviewer), fn (Builder $query) => $query->whereHas(
                'employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
            ))
            ->withSum('hourBankTransactions as hour_bank_balance', 'minutes')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'hourBankBalance' => (int) $user->getAttribute('hour_bank_balance'),
            ]);

        $exceptions = WorkScheduleException::query()
            ->when(! $canApproveTime, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $this->canReviewAll($reviewer), fn (Builder $query) => $query->whereHas(
                'user.employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
            ))
            ->with(['user:id,name', 'creator:id,name'])
            ->latest('work_date')
            ->limit(20)
            ->get()
            ->map(fn ($exception) => [
                'id' => $exception->id,
                'employee' => $exception->user->name,
                'workDate' => $exception->work_date->format('Y-m-d'),
                'type' => $exception->type,
                'startTime' => $exception->start_time ? substr($exception->start_time, 0, 5) : null,
                'breakStartTime' => $exception->break_start_time ? substr($exception->break_start_time, 0, 5) : null,
                'breakEndTime' => $exception->break_end_time ? substr($exception->break_end_time, 0, 5) : null,
                'endTime' => $exception->end_time ? substr($exception->end_time, 0, 5) : null,
                'reason' => $exception->reason,
                'status' => $exception->status,
                'creator' => $exception->creator->name,
            ]);

        $medicalCertificates = AbsenceJustification::query()
            ->when(! $reviewer->can('medical-certificates.review'), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $this->canReviewAll($reviewer), fn (Builder $query) => $query->whereHas(
                'user.employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
            ))
            ->with(['user:id,name', 'reviewer:id,name'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (AbsenceJustification $certificate) => [
                'id' => $certificate->id,
                'employee' => $certificate->user->name,
                'startsOn' => $certificate->starts_on->format('Y-m-d'),
                'endsOn' => $certificate->ends_on->format('Y-m-d'),
                'startsAt' => $certificate->starts_at ? substr($certificate->starts_at, 0, 5) : null,
                'endsAt' => $certificate->ends_at ? substr($certificate->ends_at, 0, 5) : null,
                'reason' => $certificate->reason,
                'status' => $certificate->status,
                'reviewer' => $certificate->reviewer?->name,
                'reviewNotes' => $certificate->review_notes,
                'documentUrl' => route('medical-certificates.download', $certificate),
            ]);

        $pendingTimeEntries = TimeEntry::query()
            ->where('status', 'pending')
            ->when(! $canApproveTime, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $this->canReviewAll($reviewer), fn (Builder $query) => $query->whereHas(
                'user.employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
            ))
            ->with('user:id,name')
            ->oldest('recorded_at')
            ->get()
            ->map(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'employee' => $entry->user->name,
                'recordedAt' => $entry->recorded_at->toIso8601String(),
                'type' => $entry->type,
                'source' => $entry->source,
                'reason' => $entry->reason,
            ]);

        $employeesWithPending = User::query()
            ->where('tracks_time', true)
            ->when(! $canApproveTime, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $this->canReviewAll($reviewer), fn (Builder $query) => $query->whereHas(
                'employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
            ))
            ->where(function (Builder $query): void {
                $query->whereHas('timeAdjustmentRequests', fn (Builder $adjustments) => $adjustments->where('status', 'pending'))
                    ->orWhereHas('timeEntries', fn (Builder $entries) => $entries->where('status', 'pending'));
            })
            ->with([
                'employeeProfile.department:id,name',
                'timeAdjustmentRequests' => fn ($query) => $query->where('status', 'pending')->oldest('work_date'),
                'timeEntries' => fn ($query) => $query->where('status', 'pending')->oldest('recorded_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $employee) {
                $adjustments = $employee->timeAdjustmentRequests->map(fn (TimeAdjustmentRequest $adjustment) => [
                    'id' => $adjustment->id,
                    'kind' => 'adjustment',
                    'date' => $adjustment->work_date->format('Y-m-d'),
                    'entries' => collect($adjustment->requested_entries)->map(fn (array $entry) => [
                        'type' => $entry['type'],
                        'time' => $entry['time'],
                    ])->values()->all(),
                    'reason' => $adjustment->reason,
                    'source' => 'manual',
                    'status' => 'pending',
                ]);
                $timeEntries = $employee->timeEntries->map(function (TimeEntry $entry) {
                    $localTime = $entry->recorded_at->setTimezone(config('app.business_timezone'));

                    return [
                        'id' => $entry->id,
                        'kind' => 'time_entry',
                        'date' => $localTime->format('Y-m-d'),
                        'entries' => [['type' => $entry->type, 'time' => $localTime->format('H:i')]],
                        'reason' => $entry->reason,
                        'source' => $entry->source,
                        'status' => $entry->status,
                    ];
                });

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'department' => $employee->employeeProfile?->department?->name,
                    'pendingCount' => $adjustments->count() + $timeEntries->count(),
                    'items' => $adjustments->concat($timeEntries)->sortBy('date')->values()->all(),
                ];
            });

        return Inertia::render('personnel/time-approvals/index', [
            'adjustments' => $adjustments,
            'employees' => $employees,
            'exceptions' => $exceptions,
            'medicalCertificates' => $medicalCertificates,
            'canApproveTime' => $canApproveTime,
            'pendingTimeEntries' => $pendingTimeEntries,
            'employeesWithPending' => $employeesWithPending,
        ]);
    }

    public function timeCard(Request $request, User $employee, TimeCardService $timeCardService): Response
    {
        abort_unless($request->user()->can('time-records.approve'), 403);
        abort_unless($this->canAccessEmployee($request->user(), $employee), 403);
        abort_unless($employee->tracks_time, 422, 'Este colaborador não registra ponto.');
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : CarbonImmutable::now(config('app.business_timezone'))->startOfMonth();

        return Inertia::render('virtual-office/time-card/index', [
            'timeCard' => $timeCardService->forMonth($employee, $month),
            'canRequestAdjustment' => false,
            'canSubmitMedicalCertificate' => false,
            'employeeName' => $employee->name,
            'managedView' => true,
        ]);
    }

    public function update(
        ReviewTimeAdjustmentRequest $request,
        TimeAdjustmentRequest $adjustment,
        TimeAdjustmentApprovalService $service,
    ): JsonResponse|RedirectResponse {
        abort_unless($this->canReview($request->user(), $adjustment), 403);

        $reviewed = $service->decide(
            $adjustment,
            $request->user(),
            $request->string('decision')->toString(),
            $request->string('notes')->trim()->toString() ?: null,
        );

        $message = $reviewed->status === 'approved'
            ? 'Ajuste de ponto aprovado com sucesso.'
            : 'Ajuste de ponto rejeitado.';

        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json([
            'message' => $message,
            'status' => $reviewed->status,
        ]);
    }

    private function visibleAdjustments(User $reviewer): Builder
    {
        return TimeAdjustmentRequest::query()
            ->when(! $this->canReviewAll($reviewer), fn (Builder $query) => $query->whereHas(
                'user.employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
            ));
    }

    private function canReview(User $reviewer, TimeAdjustmentRequest $adjustment): bool
    {
        if ($this->canReviewAll($reviewer)) {
            return true;
        }

        return $adjustment->user()->whereHas(
            'employeeProfile',
            fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
        )->exists();
    }

    private function canAccessEmployee(User $reviewer, User $employee): bool
    {
        if ($this->canReviewAll($reviewer)) {
            return true;
        }

        return $employee->employeeProfile()
            ->where('manager_id', $reviewer->id)
            ->exists();
    }

    private function canReviewAll(User $reviewer): bool
    {
        return $reviewer->hasRole('super-admin') || $reviewer->can('time-records.manage');
    }
}
