<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\ReviewTimeAdjustmentRequest;
use App\Models\TimeEntry;
use App\Models\User;
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

            $entry->update([
                'status' => $request->input('decision') === 'approve' ? 'approved' : 'cancelled',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $request->string('notes')->trim()->toString() ?: null,
            ]);

            return $entry;
        });

        return response()->json([
            'message' => $entry->status === 'approved' ? 'Batida aprovada.' : 'Batida rejeitada.',
            'status' => $entry->status,
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
