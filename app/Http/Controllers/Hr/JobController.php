<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\JobRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\HrAuditEvent;
use App\Models\Job;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Job::class);

        return Inertia::render('hr/jobs/index', [
            'jobs' => Job::query()->with(['company:id,unit_name,trade_name,name', 'department:id,name', 'position:id,title,level', 'hiringManager:id,name'])->withCount('applications')->latest()->get(),
            'companies' => Company::query()->where('active', true)->orderBy('unit_name')->get(['id', 'unit_name', 'trade_name', 'name']),
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(['id', 'company_id', 'name']),
            'positions' => Position::query()->where('active', true)->orderBy('title')->get()->map(fn (Position $position) => ['id' => $position->id, 'company_id' => $position->company_id, 'name' => $position->display_name]),
            'managers' => User::role('gestor')->orderBy('name')->get(['id', 'name']),
            'abilities' => ['create' => $request->user()->can('create', Job::class), 'delete' => $request->user()->can('jobs.delete')],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JobRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['company_id'], $data['title']);
        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['status'] === 'published' ? now() : null;
        $job = Job::query()->create($data);
        $this->audit($request, 'job.created', $job, null, $job->toArray());

        return response()->json(['message' => 'Vaga cadastrada com sucesso.'], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(JobRequest $request, Job $job): JsonResponse
    {
        $before = $job->toArray();
        $data = $request->validated();
        if ($job->title !== $data['title'] || $job->company_id !== (int) $data['company_id']) {
            $data['slug'] = $this->uniqueSlug($data['company_id'], $data['title'], $job->id);
        }
        $data['published_at'] = $data['status'] === 'published' ? ($job->published_at ?? now()) : null;
        $job->update($data);
        $this->audit($request, 'job.updated', $job, $before, $job->fresh()->toArray());

        return response()->json(['message' => 'Vaga atualizada com sucesso.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Job $job): JsonResponse
    {
        Gate::authorize('delete', $job);
        abort_if($job->applications()->exists(), 422, 'Vagas com candidaturas não podem ser excluídas. Encerre a vaga.');
        $before = $job->toArray();
        $job->delete();
        $this->audit($request, 'job.deleted', $job, $before, null);

        return response()->json(['message' => 'Vaga excluída com sucesso.']);
    }

    private function uniqueSlug(int $companyId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'vaga';
        $slug = $base;
        $suffix = 2;
        while (Job::withTrashed()->where('company_id', $companyId)->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function audit(Request $request, string $event, Job $job, ?array $old, ?array $new): void
    {
        HrAuditEvent::query()->create([
            'actor_id' => $request->user()->id,
            'impersonator_id' => $request->session()->get('impersonation.original_user_id'),
            'event' => $event,
            'auditable_type' => $job->getMorphClass(),
            'auditable_id' => $job->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
        ]);
    }
}
