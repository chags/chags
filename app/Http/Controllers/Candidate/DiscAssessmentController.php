<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\DiscAnswer;
use App\Models\DiscAssessment;
use App\Models\DiscQuestion;
use App\Services\DiscScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DiscAssessmentController extends Controller
{
    public function show(Request $request, Application $application): Response
    {
        $this->authorizeDisc($request, $application);
        $assessment = $application->discAssessment;

        return Inertia::render('candidate/disc/show', [
            'application' => ['id' => $application->id, 'job_title' => $application->job->title],
            'assessment' => $assessment ? [
                'status' => $assessment->status,
                'current_position' => $assessment->current_position,
                'answers' => $assessment->answers()->pluck('disc_option_id', 'disc_question_id'),
                'result' => $assessment->result_snapshot,
            ] : null,
            'questions' => DiscQuestion::query()->where('active', true)->where('version', '1.0')->with('options:id,disc_question_id,code,text,display_order')->orderBy('position')->get()->map(fn ($question) => [
                'id' => $question->id, 'position' => $question->position, 'prompt' => $question->prompt,
                'options' => $question->options->map->only(['id', 'code', 'text']),
            ]),
        ]);
    }

    public function start(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeDisc($request, $application);
        $request->validate(['consent' => ['accepted']], ['consent.accepted' => 'Você precisa confirmar a ciência sobre a finalidade do questionário.']);
        $assessment = DiscAssessment::query()->firstOrCreate(
            ['application_id' => $application->id],
            ['candidate_id' => $request->user()->id, 'status' => 'in_progress', 'questionnaire_version' => '1.0', 'current_position' => 1, 'consent_at' => now(), 'ip_address' => $request->ip(), 'started_at' => now()],
        );
        abort_if($assessment->status === 'completed', 422, 'Este teste já foi concluído e não pode ser refeito.');

        return redirect()->route('candidate.disc.show', $application);
    }

    public function answer(Request $request, Application $application, DiscQuestion $question): JsonResponse
    {
        $this->authorizeDisc($request, $application);
        $assessment = $application->discAssessment;
        abort_unless($assessment && $assessment->status === 'in_progress', 422, 'Inicie o teste antes de responder.');
        $data = $request->validate(['option_id' => ['required', Rule::exists('disc_options', 'id')->where('disc_question_id', $question->id)]]);
        DiscAnswer::query()->updateOrCreate(
            ['disc_assessment_id' => $assessment->id, 'disc_question_id' => $question->id],
            ['disc_option_id' => $data['option_id'], 'answered_at' => now()],
        );
        $assessment->update(['current_position' => min(20, $question->position + 1)]);

        return response()->json(['message' => 'Resposta salva.']);
    }

    public function complete(Request $request, Application $application, DiscScoringService $scoring): JsonResponse
    {
        $this->authorizeDisc($request, $application);
        try {
            $assessment = $scoring->complete($application->discAssessment ?? throw new RuntimeException('Inicie o teste antes de concluir.'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Teste concluído com sucesso.', 'result' => $assessment->result_snapshot]);
    }

    private function authorizeDisc(Request $request, Application $application): void
    {
        Gate::authorize('viewOwn', $application);
        $application->loadMissing(['job', 'currentStage', 'discAssessment']);
        abort_unless($application->currentStage?->candidate_action === 'disc', 403, 'O teste DISC ainda não está disponível nesta candidatura.');
    }
}
