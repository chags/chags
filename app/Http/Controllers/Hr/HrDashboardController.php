<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Job;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(
            $request->user()->can('jobs.view') || $request->user()->can('employees.view'),
            403,
        );

        return Inertia::render('hr/dashboard', [
            'metrics' => [
                'openJobs' => Job::query()->where('status', 'published')->count(),
                'activeApplications' => Application::query()->where('status', 'active')->count(),
                'activeEmployees' => EmployeeProfile::query()->where('employment_status', 'active')->count(),
                'departments' => Department::query()->where('active', true)->count(),
            ],
            'recentJobs' => Job::query()
                ->with('department:id,name')
                ->withCount('applications')
                ->latest()
                ->limit(8)
                ->get(['id', 'department_id', 'title', 'status', 'published_at'])
                ->map(fn (Job $job) => [
                    'id' => $job->id,
                    'title' => $job->title,
                    'department' => $job->department?->name,
                    'status' => $job->status,
                    'applications_count' => $job->applications_count,
                    'published_at' => $job->published_at?->toIso8601String(),
                ]),
        ]);
    }
}
