<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\ReviewTimeAdjustmentRequest;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeEntryReviewController extends Controller
{
    public function update(ReviewTimeAdjustmentRequest $request, TimeEntry $entry): JsonResponse
    {
        abort_unless($this->canReview($request->user(), $entry), 403);

        $entry = DB::transaction(function () use ($request, $entry): TimeEntry {
            $entry = TimeEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'Esta batida não está pendente de análise.',
                ]);
            }

            $user = User::query()->lockForUpdate()->findOrFail($entry->user_id);

            $entry->update([
                'status' => $request->input('decision') === 'approve' ? 'approved' : 'cancelled',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $request->string('notes')->trim()->toString() ?: null,
            ]);

            if ($entry->status === 'approved' && str_starts_with($entry->type, 'overtime_')) {
                $this->creditOvertimePair($entry, $user, $request->user());
            }

            return $entry;
        });

        return response()->json([
            'message' => $entry->status === 'approved' ? 'Batida aprovada.' : 'Batida rejeitada.',
            'status' => $entry->status,
        ]);
    }

    private function creditOvertimePair(TimeEntry $reviewedEntry, User $user, User $reviewer): void
    {
        $entries = $user->timeEntries()
            ->whereDate('work_date', $reviewedEntry->work_date)
            ->where('status', 'approved')
            ->whereIn('type', ['clock_out', 'overtime_start', 'overtime_end'])
            ->orderBy('recorded_at')
            ->get()
            ->keyBy('type');
        $clockOut = $entries->get('clock_out')?->recorded_at;
        $start = $entries->get('overtime_start')?->recorded_at;
        $end = $entries->get('overtime_end')?->recorded_at;

        if (! $clockOut) {
            throw ValidationException::withMessages([
                'decision' => 'A hora extra só pode ser aprovada depois da saída normal.',
            ]);
        }
        if ($start && $start->lte($clockOut)) {
            throw ValidationException::withMessages([
                'decision' => 'A hora extra deve começar depois da saída normal.',
            ]);
        }
        if ($reviewedEntry->type === 'overtime_end' && ! $start) {
            throw ValidationException::withMessages([
                'decision' => 'Aprove o início da hora extra antes do término.',
            ]);
        }
        if (! $start || ! $end) {
            return;
        }
        if ($end->lte($start) || $start->diffInMinutes($end) > 120) {
            throw ValidationException::withMessages([
                'decision' => 'A hora extra deve terminar depois do início e não pode ultrapassar 2 horas.',
            ]);
        }

        $alreadyCredited = $user->hourBankTransactions()
            ->whereDate('work_date', $reviewedEntry->work_date)
            ->where('type', 'overtime')
            ->exists();
        if ($alreadyCredited) {
            return;
        }

        $user->hourBankTransactions()->create([
            'work_date' => CarbonImmutable::parse($reviewedEntry->work_date)->toDateString(),
            'minutes' => (int) $start->diffInMinutes($end),
            'type' => 'overtime',
            'description' => 'Período de hora extra aprovado pelo gestor.',
            'created_by' => $reviewer->id,
        ]);
    }

    private function canReview(User $reviewer, TimeEntry $entry): bool
    {
        if ($reviewer->hasRole('super-admin') || $reviewer->can('time-records.manage')) {
            return true;
        }

        return $entry->user()->whereHas(
            'employeeProfile',
            fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
        )->exists();
    }
}
