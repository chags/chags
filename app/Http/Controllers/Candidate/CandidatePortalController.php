<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CandidatePortalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('applications.view-own'), 403);

        $applications = Application::query()
            ->where('candidate_id', $request->user()->id)
            ->with([
                'job:id,company_id,department_id,title,slug,image,workplace_type,employment_type,city,state,status',
                'job.company:id,unit_name,trade_name,name',
                'job.department:id,name',
                'currentStage:id,name,public_name',
                'curriculum:id,application_id,extraction_status,evaluation_status',
            ])
            ->latest('applied_at')
            ->get()
            ->map(fn (Application $application) => $this->card($application));

        return Inertia::render('candidate/index', [
            'candidate' => ['firstName' => str($request->user()->name)->before(' ')->toString()],
            'applications' => $applications,
        ]);
    }

    private function card(Application $application): array
    {
        return [
            'id' => $application->id,
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
                'status' => $application->job->status,
            ],
            'status' => $application->status,
            'current_stage' => $application->currentStage?->public_name
                ?: $application->currentStage?->name
                ?: $this->analysisLabel($application),
            'applied_at' => $application->applied_at?->toIso8601String(),
        ];
    }

    private function analysisLabel(Application $application): string
    {
        return match (true) {
            $application->curriculum?->evaluation_status === 'completed' => 'Análise do perfil concluída',
            $application->curriculum?->extraction_status === 'completed' => 'Análise do perfil',
            default => 'Candidatura recebida',
        };
    }
}
