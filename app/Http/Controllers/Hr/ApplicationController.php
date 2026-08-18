<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApplicationUpdateRequest;
use App\Models\Application;
use App\Models\ApplicationStageHistory;
use App\Models\HrAuditEvent;
use App\Models\RecruitmentStage;
use App\Services\ResumeExtractionService;
use App\Services\ResumeScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Application::class);

        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $status = in_array($status, ['active', 'rejected', 'withdrawn', 'hired'], true) ? $status : '';
        $searchTerm = '%'.mb_strtolower($search).'%';

        $applications = Application::query()
            ->with([
                'candidate:id,name,email,phone',
                'candidate.candidateProfile:id,user_id,city,state',
                'job:id,company_id,department_id,title',
                'job.company:id,unit_name,trade_name,name',
                'job.department:id,name',
                'currentStage:id,name',
                'curriculum',
                'discAssessment:id,application_id,status,current_position,d_score,i_score,s_score,c_score,dominant_profile,result_snapshot,started_at,completed_at',
            ])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where(function ($query) use ($searchTerm) {
                $query
                    ->whereHas('candidate', fn ($candidate) => $candidate
                        ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm]))
                    ->orWhereHas('job', fn ($job) => $job
                        ->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                        ->orWhereHas('department', fn ($department) => $department
                            ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])))
                    ->orWhereHas('currentStage', fn ($stage) => $stage
                        ->whereRaw('LOWER(name) LIKE ?', [$searchTerm]));
            }))
            ->latest('applied_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Application $application) => [
                'id' => $application->id,
                'candidate' => [
                    'name' => $application->candidate->name,
                    'email' => $application->candidate->email,
                    'phone' => $application->candidate->phone,
                    'city' => $application->candidate->candidateProfile?->city,
                    'state' => $application->candidate->candidateProfile?->state,
                ],
                'job' => [
                    'company_id' => $application->job->company_id,
                    'title' => $application->job->title,
                    'company' => $application->job->company->trade_name ?: $application->job->company->name,
                    'unit' => $application->job->company->unit_name,
                    'department' => $application->job->department->name,
                ],
                'current_stage_id' => $application->current_stage_id,
                'current_stage' => $application->currentStage?->name,
                'status' => $application->status,
                'rejection_message' => $application->rejection_message,
                'rejection_internal_notes' => $application->rejection_internal_notes,
                'source' => $application->source,
                'resume_original_name' => $application->resume_original_name,
                'resume_size' => $application->resume_size,
                'privacy_consent_at' => $application->privacy_consent_at?->toIso8601String(),
                'applied_at' => $application->applied_at?->toIso8601String(),
                'curriculum' => $application->curriculum ? [
                    'extraction_status' => $application->curriculum->extraction_status,
                    'evaluation_status' => $application->curriculum->evaluation_status,
                    'score' => $application->curriculum->score,
                    'opinion' => $application->curriculum->opinion,
                    'recommendation' => $application->curriculum->recommendation,
                    'extracted_data' => $application->curriculum->extracted_data,
                    'strengths' => $application->curriculum->strengths ?? [],
                    'concerns' => $application->curriculum->concerns ?? [],
                    'matched_requirements' => $application->curriculum->matched_requirements ?? [],
                    'missing_requirements' => $application->curriculum->missing_requirements ?? [],
                    'extraction_attempts' => $application->curriculum->extraction_attempts,
                    'evaluation_attempts' => $application->curriculum->evaluation_attempts,
                    'extraction_error' => $application->curriculum->extraction_error,
                    'evaluation_error' => $application->curriculum->evaluation_error,
                    'extracted_at' => $application->curriculum->extracted_at?->toIso8601String(),
                    'evaluated_at' => $application->curriculum->evaluated_at?->toIso8601String(),
                    'last_attempted_at' => $application->curriculum->last_attempted_at?->toIso8601String(),
                ] : null,
                'disc_assessment' => $application->discAssessment ? [
                    'status' => $application->discAssessment->status,
                    'current_position' => $application->discAssessment->current_position,
                    'dominant_profile' => $application->discAssessment->dominant_profile,
                    'label' => $application->discAssessment->result_snapshot['label'] ?? null,
                    'scores' => [
                        'D' => $application->discAssessment->d_score,
                        'I' => $application->discAssessment->i_score,
                        'S' => $application->discAssessment->s_score,
                        'C' => $application->discAssessment->c_score,
                    ],
                    'started_at' => $application->discAssessment->started_at?->toIso8601String(),
                    'completed_at' => $application->discAssessment->completed_at?->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('hr/applications/index', [
            'applications' => $applications,
            'filters' => ['search' => $search, 'status' => $status],
            'stages' => RecruitmentStage::query()->where('active', true)->orderBy('position')->get(['id', 'company_id', 'name']),
            'abilities' => [
                'update' => $request->user()->can('applications.update-status'),
                'delete' => $request->user()->can('applications.delete'),
                'screen' => $request->user()->can('applications.evaluate'),
            ],
        ]);
    }

    public function extract(Request $request, Application $application, ResumeExtractionService $resumeExtraction): JsonResponse
    {
        abort_unless($request->user()->can('applications.evaluate'), 403);
        $curriculum = $resumeExtraction->extract($application);

        return response()->json([
            'message' => $curriculum->extraction_status === 'completed'
                ? 'Dados do currículo extraídos e armazenados com sucesso.'
                : 'O currículo permanece salvo, mas seus dados não puderam ser extraídos.',
            'status' => $curriculum->extraction_status,
        ], $curriculum->extraction_status === 'completed' ? 200 : 422);
    }

    public function screen(Request $request, Application $application, ResumeScreeningService $resumeScreening): JsonResponse
    {
        abort_unless($request->user()->can('applications.evaluate'), 403);

        $curriculum = $resumeScreening->screen($application);
        $this->audit($request, 'application.resume-screened', $application, null, [
            'status' => $curriculum->status,
            'score' => $curriculum->score,
            'attempts' => $curriculum->attempts,
        ]);

        return response()->json([
            'message' => $curriculum->evaluation_status === 'completed'
                ? 'Triagem por IA concluída com sucesso.'
                : 'Não foi possível concluir a triagem. O currículo permanece salvo e pode ser processado novamente.',
            'status' => $curriculum->evaluation_status,
        ], $curriculum->evaluation_status === 'completed' ? 200 : 422);
    }

    public function update(ApplicationUpdateRequest $request, Application $application): JsonResponse
    {
        $data = $request->validated();
        $before = $application->only(['status', 'current_stage_id', 'rejected_at', 'rejection_message', 'rejection_internal_notes', 'withdrawn_at', 'hired_at']);
        $oldStageId = $application->current_stage_id;
        $newStageId = filled($data['current_stage_id'] ?? null) ? (int) $data['current_stage_id'] : null;

        $application->fill([
            'status' => $data['status'],
            'current_stage_id' => $newStageId,
            'rejected_at' => $data['status'] === 'rejected' ? ($application->rejected_at ?? now()) : null,
            'rejection_message' => $data['status'] === 'rejected' ? $data['rejection_message'] : null,
            'rejection_internal_notes' => $data['status'] === 'rejected' ? ($data['rejection_internal_notes'] ?? null) : null,
            'withdrawn_at' => $data['status'] === 'withdrawn' ? ($application->withdrawn_at ?? now()) : null,
            'hired_at' => $data['status'] === 'hired' ? ($application->hired_at ?? now()) : null,
        ])->save();

        if ($newStageId && $newStageId !== $oldStageId) {
            ApplicationStageHistory::query()->create([
                'application_id' => $application->id,
                'from_stage_id' => $oldStageId,
                'to_stage_id' => $newStageId,
                'changed_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        $this->audit($request, 'application.updated', $application, $before, $application->fresh()->only(['status', 'current_stage_id', 'rejected_at', 'rejection_message', 'rejection_internal_notes', 'withdrawn_at', 'hired_at']));

        return response()->json(['message' => 'Candidatura atualizada com sucesso.']);
    }

    public function destroy(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('delete', $application);
        $before = $application->toArray();
        $application->delete();
        $this->audit($request, 'application.deleted', $application, $before, null);

        return response()->json(['message' => 'Candidatura excluída com sucesso.']);
    }

    private function audit(Request $request, string $event, Application $application, ?array $old, ?array $new): void
    {
        HrAuditEvent::query()->create([
            'actor_id' => $request->user()->id,
            'impersonator_id' => $request->session()->get('impersonation.original_user_id'),
            'event' => $event,
            'auditable_type' => $application->getMorphClass(),
            'auditable_id' => $application->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
        ]);
    }
}
