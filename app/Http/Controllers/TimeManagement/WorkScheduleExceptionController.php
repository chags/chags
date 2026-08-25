<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\StoreWorkScheduleExceptionRequest;
use App\Models\User;
use App\Models\WorkScheduleException;
use App\Services\VirtualOffice\WorkScheduleExceptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkScheduleExceptionController extends Controller
{
    public function store(StoreWorkScheduleExceptionRequest $request, WorkScheduleExceptionService $service): JsonResponse
    {
        $user = User::query()->findOrFail($request->integer('user_id'));
        abort_unless($this->canManage($request->user(), $user), 403);

        $exception = $service->create($user, $request->user(), $request->validated());

        return response()->json([
            'message' => $exception->type === 'hour_bank_leave'
                ? 'Folga pelo banco de horas registrada com sucesso.'
                : 'Exceção individual de jornada registrada com sucesso.',
        ], 201);
    }

    public function destroy(Request $request, WorkScheduleException $exception): JsonResponse
    {
        abort_unless($request->user()->can('time-records.approve'), 403);
        abort_unless($this->canManage($request->user(), $exception->user), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        DB::transaction(function () use ($request, $exception, $data): void {
            $exception = WorkScheduleException::query()->lockForUpdate()->findOrFail($exception->id);
            if ($exception->status !== 'approved') {
                throw ValidationException::withMessages(['reason' => 'Esta exceção já foi cancelada.']);
            }

            if ($exception->type === 'hour_bank_leave') {
                $debitedMinutes = (int) $exception->hourBankTransactions()->sum('minutes');
                $exception->hourBankTransactions()->create([
                    'user_id' => $exception->user_id,
                    'work_date' => $exception->work_date,
                    'minutes' => -$debitedMinutes,
                    'type' => 'reversal',
                    'description' => 'Estorno do cancelamento da folga compensatória.',
                    'created_by' => $request->user()->id,
                ]);
            }

            $exception->update([
                'status' => 'cancelled',
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);
        });

        return response()->json(['message' => 'Exceção cancelada com sucesso.']);
    }

    private function canManage(User $manager, User $employee): bool
    {
        if ($manager->hasRole('super-admin') || $manager->can('time-records.manage')) {
            return true;
        }

        return $employee->employeeProfile()
            ->where(fn (Builder $query) => $query->where('manager_id', $manager->id))
            ->exists();
    }
}
