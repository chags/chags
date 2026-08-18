<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\InterviewScheduleRequest;
use App\Models\Application;
use App\Models\ApplicationStageHistory;
use App\Models\HrAuditEvent;
use App\Models\InterviewNotificationDelivery;
use App\Models\InterviewSchedule;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Notifications\InterviewScheduledNotification;
use App\Services\InterviewWhatsAppLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InterviewScheduleController extends Controller
{
    public function index(InterviewWhatsAppLinkService $whatsapp)
    {
        Gate::authorize('viewAny', InterviewSchedule::class);
        $items = InterviewSchedule::with(['application.candidate:id,name,email,phone', 'application.job:id,title', 'stage:id,name,public_name', 'organizer:id,name'])->latest('starts_at')->paginate(15)->through(fn ($s) => [
            'id' => $s->id, 'candidate' => $s->application->candidate, 'job' => $s->application->job->title, 'stage' => $s->stage->public_name ?: $s->stage->name, 'organizer' => $s->organizer->name, 'format' => $s->format, 'status' => $s->status, 'starts_at' => $s->starts_at->toIso8601String(), 'ends_at' => $s->ends_at->toIso8601String(), 'timezone' => $s->timezone, 'location' => $s->location, 'meeting_url' => $s->meeting_url, 'candidate_response' => $s->candidate_response, 'whatsapp_url' => $whatsapp->make($s),
        ]);

        $eligible = Application::query()->with(['candidate:id,name,email,phone', 'job:id,company_id,title', 'discAssessment:id,application_id,dominant_profile,completed_at'])
            ->where('status', 'active')->whereHas('currentStage', fn ($query) => $query->where('candidate_action', 'disc'))
            ->whereHas('discAssessment', fn ($query) => $query->where('status', 'completed'))
            ->whereDoesntHave('interviewSchedules', fn ($query) => $query->whereHas('stage', fn ($stage) => $stage->where('name', 'Entrevista com RH'))->whereNot('status', 'cancelled'))
            ->latest('applied_at')->get()->map(fn ($application) => ['id' => $application->id, 'candidate' => $application->candidate, 'job' => $application->job->title, 'disc_profile' => $application->discAssessment->dominant_profile, 'disc_completed_at' => $application->discAssessment->completed_at?->toIso8601String()]);

        return Inertia::render('hr/evaluations/index', ['schedules' => $items, 'eligibleApplications' => $eligible, 'organizers' => User::permission('interviews.create')->orderBy('name')->get(['id', 'name', 'email'])]);
    }

    public function store(InterviewScheduleRequest $request)
    {
        $data = $request->validated();
        $application = Application::with(['job', 'currentStage', 'discAssessment'])->findOrFail($data['application_id']);
        abort_unless($application->status === 'active' && $application->currentStage?->candidate_action === 'disc' && $application->discAssessment?->status === 'completed', 422, 'Esta candidatura ainda não está pronta para a entrevista com RH.');
        $stage = RecruitmentStage::where('company_id', $application->job->company_id)->where('name', 'Entrevista com RH')->where('active', true)->firstOrFail();
        abort_if($application->interviewSchedules()->where('stage_id', $stage->id)->whereNot('status', 'cancelled')->exists(), 422, 'Esta entrevista com RH já foi agendada.');
        $starts = Carbon::parse($data['starts_at'], $data['timezone'])->utc();
        $ends = $starts->copy()->addMinutes((int) $data['duration_minutes']);
        abort_if($starts->isPast(), 422, 'Escolha um horário futuro.');
        $conflict = InterviewSchedule::where('organizer_id', $data['organizer_id'])->whereNotIn('status', ['cancelled'])->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)->exists();
        abort_if($conflict, 422, 'O organizador já possui uma entrevista neste horário.');
        unset($data['duration_minutes'], $data['send_email']);
        $schedule = InterviewSchedule::create([...$data, 'stage_id' => $stage->id, 'provider' => $data['format'] === 'online' ? 'manual' : $data['format'], 'status' => 'scheduled', 'starts_at' => $starts, 'ends_at' => $ends, 'created_by' => $request->user()->id]);
        ApplicationStageHistory::create(['application_id' => $application->id, 'from_stage_id' => $application->current_stage_id, 'to_stage_id' => $stage->id, 'changed_by' => $request->user()->id, 'notes' => 'Entrevista com RH agendada.']);
        $application->update(['current_stage_id' => $stage->id]);
        if ($request->boolean('send_email')) {
            $application->candidate->notify(new InterviewScheduledNotification($schedule->id));
            InterviewNotificationDelivery::create(['interview_schedule_id' => $schedule->id, 'recipient' => $application->candidate->email, 'channel' => 'mail', 'status' => 'queued']);
        }
        HrAuditEvent::create(['actor_id' => $request->user()->id, 'event' => 'interview.scheduled', 'auditable_type' => $schedule->getMorphClass(), 'auditable_id' => $schedule->id, 'new_values' => $schedule->only(['application_id', 'stage_id', 'starts_at', 'ends_at', 'status']), 'ip_address' => $request->ip()]);

        return back()->with('success', 'Entrevista agendada com sucesso.');
    }
}
