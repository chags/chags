<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\ReviewMedicalCertificateRequest;
use App\Models\AbsenceJustification;
use App\Models\User;
use App\Services\VirtualOffice\MedicalCertificateApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicalCertificateReviewController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('medical-certificates.review'), 403);
        $reviewer = $request->user();
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,cancelled'],
        ]);

        $documents = AbsenceJustification::query()
            ->when(! $this->canReviewAll($reviewer), fn (Builder $query) => $query->whereHas(
                'user.employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $reviewer->id),
            ))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['user:id,name,email', 'user.employeeProfile.department:id,name', 'reviewer:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AbsenceJustification $document) => [
                'id' => $document->id,
                'employee' => $document->user->name,
                'email' => $document->user->email,
                'department' => $document->user->employeeProfile?->department?->name,
                'type' => $document->type,
                'startsOn' => $document->starts_on->format('Y-m-d'),
                'endsOn' => $document->ends_on->format('Y-m-d'),
                'startsAt' => $document->starts_at ? substr($document->starts_at, 0, 5) : null,
                'endsAt' => $document->ends_at ? substr($document->ends_at, 0, 5) : null,
                'reason' => $document->reason,
                'status' => $document->status,
                'reviewer' => $document->reviewer?->name,
                'reviewNotes' => $document->review_notes,
                'createdAt' => $document->created_at->toIso8601String(),
                'documentUrl' => route('medical-certificates.download', $document),
            ]);

        return Inertia::render('personnel/medical-certificates/index', [
            'documents' => $documents,
            'filters' => ['status' => $validated['status'] ?? null],
        ]);
    }

    public function update(ReviewMedicalCertificateRequest $request, AbsenceJustification $justification, MedicalCertificateApprovalService $approvalService): JsonResponse
    {
        abort_unless($this->canReview($request->user(), $justification), 403);
        $notes = $request->string('notes')->trim()->toString();
        $justification = $request->input('decision') === 'approve'
            ? $approvalService->approve($justification, $request->user(), $notes ?: null)
            : $approvalService->reject($justification, $request->user(), $notes);

        return response()->json([
            'message' => $justification->status === 'approved' ? 'Atestado aprovado.' : 'Atestado rejeitado.',
            'status' => $justification->status,
        ]);
    }

    private function canReviewAll(User $reviewer): bool
    {
        return $reviewer->hasRole('super-admin') || $reviewer->can('time-records.manage');
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
