<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\ReviewMedicalCertificateRequest;
use App\Models\AbsenceJustification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalCertificateReviewController extends Controller
{
    public function update(ReviewMedicalCertificateRequest $request, AbsenceJustification $justification): JsonResponse
    {
        abort_unless($this->canReview($request->user(), $justification), 403);

        $justification = DB::transaction(function () use ($request, $justification): AbsenceJustification {
            $justification = AbsenceJustification::query()->lockForUpdate()->findOrFail($justification->id);
            if ($justification->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Este atestado já foi analisado.']);
            }

            $justification->update([
                'status' => $request->input('decision') === 'approve' ? 'approved' : 'cancelled',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $request->string('notes')->trim()->toString() ?: null,
            ]);

            return $justification;
        });

        return response()->json([
            'message' => $justification->status === 'approved' ? 'Atestado aprovado.' : 'Atestado rejeitado.',
            'status' => $justification->status,
        ]);
    }

    private function canReview(User $reviewer, AbsenceJustification $justification): bool
    {
        if ($reviewer->hasRole('super-admin') || $reviewer->can('time-records.manage')) {
            return true;
        }

        return $justification->user()->whereHas(
            'employeeProfile',
            fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
        )->exists();
    }
}
