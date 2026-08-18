<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\RecruitmentStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CandidateApplicationController extends Controller
{
    public function show(Request $request, Application $application): Response
    {
        Gate::authorize('viewOwn', $application);
        $application->load([
            'job:id,company_id,department_id,title,slug,image,workplace_type,employment_type,city,state,status',
            'job.company:id,unit_name,trade_name,name',
            'job.department:id,name',
            'currentStage',
            'curriculum:id,application_id,extraction_status,evaluation_status,evaluated_at',
            'stageHistories:id,application_id,to_stage_id,created_at',
            'discAssessment:id,application_id,status,result_snapshot,completed_at',
            'interviewSchedules' => fn ($query) => $query->whereNot('status', 'cancelled')->with('stage:id,public_name,name')->orderBy('starts_at'),
        ]);
        abort_unless(in_array($application->job->status, ['published', 'closed'], true), 403, 'Esta vaga não está disponível para acompanhamento.');

        return Inertia::render('candidate/applications/show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'applied_at' => $application->applied_at?->toIso8601String(),
                'job' => [
                    'title' => $application->job->title,
                    'slug' => $application->job->slug,
                    'image_url' => $application->job->image_url,
                    'company' => $application->job->company->trade_name ?: $application->job->company->name,
                    'unit' => $application->job->company->unit_name,
                    'department' => $application->job->department->name,
                    'workplace_type' => $application->job->workplace_type,
                    'employment_type' => $application->job->employment_type,
                    'city' => $application->job->city,
                    'state' => $application->job->state,
                ],
                'timeline' => $this->timeline($application),
                'interviews' => $application->interviewSchedules->map(fn ($schedule) => [
                    'id' => $schedule->id, 'stage' => $schedule->stage->public_name ?: $schedule->stage->name,
                    'starts_at' => $schedule->starts_at?->toIso8601String(), 'ends_at' => $schedule->ends_at?->toIso8601String(), 'timezone' => $schedule->timezone,
                    'format' => $schedule->format, 'location' => $schedule->location, 'meeting_url' => $schedule->meeting_url,
                    'instructions' => $schedule->public_instructions, 'response' => $schedule->candidate_response,
                ]),
            ],
        ]);
    }

    private function timeline(Application $application): array
    {
        $timeline = collect([[
            'key' => 'received',
            'name' => 'Candidatura recebida',
            'description' => 'Recebemos sua candidatura e seu currículo com segurança.',
            'status' => 'completed',
            'completed_at' => $application->applied_at?->toIso8601String(),
            'action' => null,
        ]]);

        $timeline->push([
            'key' => 'profile-analysis',
            'name' => 'Análise do perfil',
            'description' => 'Seu perfil está sendo comparado aos requisitos da oportunidade.',
            'status' => $application->curriculum?->evaluation_status === 'completed' ? 'completed' : 'current',
            'completed_at' => $application->curriculum?->evaluation_status === 'completed' ? $application->curriculum->evaluated_at?->toIso8601String() : null,
            'action' => null,
        ]);

        $stages = RecruitmentStage::query()
            ->where('company_id', $application->job->company_id)
            ->where('active', true)
            ->where('candidate_visible', true)
            ->orderBy('position')
            ->get();
        $completed = $application->stageHistories->pluck('created_at', 'to_stage_id');

        $currentPosition = $stages->firstWhere('id', $application->current_stage_id)?->position;
        $isRejected = $application->status === 'rejected';

        $timeline = $timeline->concat($stages->map(function (RecruitmentStage $stage) use ($application, $completed, $currentPosition, $isRejected) {
            $isCurrent = $application->current_stage_id === $stage->id;
            $completedAt = $completed->get($stage->id);
            $isBlocked = $isRejected && $currentPosition !== null && $stage->position > $currentPosition;
            $status = $isCurrent && $isRejected
                ? 'rejected'
                : ($isBlocked ? 'blocked' : ($isCurrent ? ($stage->candidate_action ? 'action_required' : 'current') : ($completedAt ? 'completed' : 'pending')));

            return [
                'key' => 'stage-'.$stage->id,
                'name' => $stage->public_name ?: $stage->name,
                'description' => $stage->public_description,
                'status' => $status,
                'completed_at' => $completedAt?->toIso8601String(),
                'action' => ! $isRejected && $isCurrent && $stage->candidate_action === 'disc' && $application->discAssessment?->status !== 'completed'
                    ? [
                        'type' => 'disc',
                        'label' => $application->discAssessment ? 'Continuar teste DISC' : 'Fazer teste DISC',
                        'url' => route('candidate.disc.show', $application),
                    ]
                    : null,
                'result' => $stage->candidate_action === 'disc' && $application->discAssessment?->status === 'completed'
                    ? $application->discAssessment->result_snapshot
                    : null,
                'status' => ! $isRejected && $stage->candidate_action === 'disc' && $application->discAssessment?->status === 'completed'
                    ? 'completed'
                    : $status,
            ];
        }));

        if (in_array($application->status, ['rejected', 'hired', 'withdrawn'], true)) {
            $timeline->push([
                'key' => 'result',
                'name' => match ($application->status) {
                    'hired' => 'Processo concluído',
                    'withdrawn' => 'Candidatura retirada',
                    default => 'Processo encerrado',
                },
                'description' => match ($application->status) {
                    'hired' => 'Parabéns! A equipe de RH entrará em contato com as próximas orientações.',
                    'withdrawn' => 'Esta candidatura foi retirada.',
                    default => $application->rejection_message ?: 'Agradecemos sua participação e o interesse nesta oportunidade.',
                },
                'status' => $application->status === 'rejected' ? 'rejected' : 'completed',
                'completed_at' => ($application->hired_at ?: $application->withdrawn_at ?: $application->rejected_at)?->toIso8601String(),
                'action' => null,
            ]);
        }

        return $timeline->values()->all();
    }
}
