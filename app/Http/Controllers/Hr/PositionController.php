<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\PositionRequest;
use App\Models\Admission;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\HrAuditEvent;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Position::class);
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $searchTerm = '%'.mb_strtolower($search).'%';

        $positions = Position::query()
            ->with(['company:id,unit_name', 'department:id,name'])
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->when($search, fn ($query) => $query->where(function ($query) use ($searchTerm) {
                $query
                    ->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', [$searchTerm])
                    ->orWhereHas('department', fn ($department) => $department->whereRaw('LOWER(name) LIKE ?', [$searchTerm]));
            }))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('hr/positions/index', [
            'positions' => $positions,
            'companies' => Company::query()->where('active', true)->orderBy('unit_name')->get(['id', 'unit_name']),
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(['id', 'company_id', 'name']),
            'filters' => ['search' => $search, 'status' => in_array($status, ['active', 'inactive'], true) ? $status : ''],
            'canManage' => $request->user()->can('positions.manage'),
        ]);
    }

    public function store(PositionRequest $request): JsonResponse
    {
        $position = Position::query()->create($this->normalized($request->validated()));
        $this->audit($request, 'position.created', $position, null, $position->toArray());

        return response()->json(['message' => 'Cargo cadastrado com sucesso.'], 201);
    }

    public function update(PositionRequest $request, Position $position): JsonResponse
    {
        $before = $position->toArray();
        $position->update($this->normalized($request->validated()));
        $this->audit($request, 'position.updated', $position, $before, $position->fresh()->toArray());

        return response()->json(['message' => 'Cargo atualizado com sucesso.']);
    }

    public function destroy(Request $request, Position $position): JsonResponse
    {
        Gate::authorize('delete', $position);
        $hasLinks = EmployeeProfile::query()->where('position_id', $position->id)->exists()
            || Admission::query()->where('position_id', $position->id)->exists();
        abort_if($hasLinks, 422, 'O cargo possui colaboradores ou admissões vinculadas e não pode ser excluído.');
        $before = $position->toArray();
        $position->delete();
        $this->audit($request, 'position.deleted', $position, $before, null);

        return response()->json(['message' => 'Cargo excluído com sucesso.']);
    }

    private function normalized(array $data): array
    {
        $data['department_id'] = $data['department_id'] ?? null;
        $data['level'] = $data['level'] ?? null;
        $data['code'] = filled($data['code'] ?? null) ? mb_strtoupper(trim($data['code'])) : null;

        return $data;
    }

    private function audit(Request $request, string $event, Position $position, ?array $old, ?array $new): void
    {
        HrAuditEvent::query()->create([
            'actor_id' => $request->user()->id,
            'impersonator_id' => $request->session()->get('impersonation.original_user_id'),
            'event' => $event,
            'auditable_type' => $position->getMorphClass(),
            'auditable_id' => $position->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
        ]);
    }
}
