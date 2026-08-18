<?php

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\TurnstileSetting;
use Inertia\Inertia;
use Inertia\Response;

class CareerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('careers/index', ['jobs' => $this->visibleJobs()->latest('published_at')->get()->map(fn (Job $job) => $this->jobData($job))]);
    }

    public function show(string $slug): Response
    {
        $job = $this->visibleJobs()->where('slug', $slug)->firstOrFail();
        $turnstile = TurnstileSetting::query()->first();
        $siteKey = app()->environment('local')
            ? config('services.turnstile.local_site_key')
            : $turnstile?->site_key;
        $turnstileEnabled = $this->acceptingApplications($job)
            && $turnstile?->enabled === true
            && filled($siteKey)
            && filled($turnstile->secret_key);

        return Inertia::render('careers/show', [
            'job' => $this->jobData($job, true),
            'turnstile' => [
                'enabled' => $turnstileEnabled,
                'siteKey' => $turnstileEnabled ? $siteKey : null,
            ],
        ]);
    }

    private function visibleJobs()
    {
        return Job::query()->with(['company:id,unit_name,trade_name,name', 'department:id,name', 'position:id,title,level'])->whereIn('status', ['published', 'closed']);
    }

    private function jobData(Job $job, bool $details = false): array
    {
        return ['id' => $job->id, 'slug' => $job->slug, 'title' => $job->title, 'image_url' => $job->image_url, 'company' => $job->company->trade_name ?: $job->company->name, 'unit' => $job->company->unit_name, 'department' => $job->department->name, 'position' => $job->position?->display_name, 'workplace_type' => $job->workplace_type, 'employment_type' => $job->employment_type, 'city' => $job->city, 'state' => $job->state, 'closes_at' => $job->closes_at?->format('Y-m-d'), 'accepting_applications' => $this->acceptingApplications($job), ...($details ? ['description' => $job->description, 'requirements' => $job->requirements, 'benefits' => $job->benefits] : [])];
    }

    private function acceptingApplications(Job $job): bool
    {
        return $job->status === 'published'
            && ($job->closes_at === null || $job->closes_at->isToday() || $job->closes_at->isFuture());
    }
}
