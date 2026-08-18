<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\DepartmentRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\HrAuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Department::class);

        return Inertia::render('hr/departments/index', [
            'departments' => Department::query()->with(['company:id,unit_name', 'parent:id,name'])->withCount(['children', 'positions'])->orderBy('name')->get(),
            'companies' => Company::query()->where('active', true)->orderBy('unit_name')->get(['id', 'unit_name']),
            'canManage' => $request->user()->can('departments.manage'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DepartmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['company_id'], $data['name']);
        $department = Department::query()->create($data);
        $this->audit($request, 'department.created', $department, null, $department->toArray());

        return response()->json(['message' => 'Setor cadastrado com sucesso.'], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DepartmentRequest $request, Department $department): JsonResponse
    {
        $before = $department->toArray();
        $data = $request->validated();
        if ($department->name !== $data['name'] || $department->company_id !== (int) $data['company_id']) {
            $data['slug'] = $this->uniqueSlug($data['company_id'], $data['name'], $department->id);
        }
        abort_if(($data['parent_id'] ?? null) && $department->children()->whereKey($data['parent_id'])->exists(), 422, 'Um setor filho não pode ser o setor superior.');
        $department->update($data);
        $this->audit($request, 'department.updated', $department, $before, $department->fresh()->toArray());

        return response()->json(['message' => 'Setor atualizado com sucesso.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);
        abort_if($department->children()->exists() || $department->positions()->exists() || $department->jobs()->exists(), 422, 'O setor possui vínculos e não pode ser excluído.');
        $before = $department->toArray();
        $department->delete();
        $this->audit($request, 'department.deleted', $department, $before, null);

        return response()->json(['message' => 'Setor excluído com sucesso.']);
    }

    private function uniqueSlug(int $companyId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'setor';
        $slug = $base;
        $suffix = 2;
        while (Department::withTrashed()->where('company_id', $companyId)->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function audit(Request $request, string $event, Department $department, ?array $old, ?array $new): void
    {
        HrAuditEvent::query()->create(['actor_id' => $request->user()->id, 'impersonator_id' => $request->session()->get('impersonation.original_user_id'), 'event' => $event, 'auditable_type' => $department->getMorphClass(), 'auditable_id' => $department->id, 'old_values' => $old, 'new_values' => $new, 'ip_address' => $request->ip()]);
    }
}
