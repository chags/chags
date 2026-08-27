<?php

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\WorkScheduleGroupRequest;
use App\Models\Company;
use App\Models\WorkScheduleGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TimeCardSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('time-records.manage'), 403);

        return Inertia::render('personnel/time-card-settings/index', [
            'groups' => WorkScheduleGroup::query()->with(['days', 'assignments.user:id,name'])->withCount(['assignments' => fn ($query) => $query->where('active', true)])->orderBy('name')->get(),
            'metrics' => [
                'activeGroups' => WorkScheduleGroup::query()->where('active', true)->count(),
                'totalGroups' => WorkScheduleGroup::query()->count(),
            ],
            'companies' => Company::query()->where('active', true)->orderBy('unit_name')->get(['id', 'unit_name', 'city', 'state']),
        ]);
    }

    public function store(WorkScheduleGroupRequest $request): JsonResponse
    {
        $group = $this->persistGroup($request, new WorkScheduleGroup);

        return response()->json(['message' => 'Grupo de jornada criado com sucesso.', 'id' => $group->id], 201);
    }

    public function update(WorkScheduleGroupRequest $request, WorkScheduleGroup $group): JsonResponse
    {
        $this->persistGroup($request, $group);

        return response()->json(['message' => 'Grupo de jornada atualizado com sucesso.']);
    }

    public function destroy(Request $request, WorkScheduleGroup $group): JsonResponse
    {
        abort_unless($request->user()->can('time-records.manage'), 403);
        abort_if($group->assignments()->where('active', true)->exists(), 422, 'Remova os usuários ativos antes de excluir o grupo.');
        $group->delete();

        return response()->json(['message' => 'Grupo excluído com sucesso.']);
    }

    private function persistGroup(WorkScheduleGroupRequest $request, WorkScheduleGroup $group): WorkScheduleGroup
    {
        return DB::transaction(function () use ($request, $group): WorkScheduleGroup {
            $data = $request->validated();
            $days = Arr::pull($data, 'days');
            if (! $group->exists) {
                $data['created_by'] = $request->user()->id;
            }
            $group->fill($data)->save();
            $group->days()->delete();
            $group->days()->createMany($days);

            return $group;
        });
    }
}
