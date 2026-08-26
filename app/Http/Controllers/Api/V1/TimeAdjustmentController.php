<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VirtualOffice\StoreTimeAdjustmentRequest;
use App\Models\TimeAdjustmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeAdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->timeAdjustmentRequests()->latest()->paginate(20)->through(fn (TimeAdjustmentRequest $item) => $this->resource($item));

        return response()->json($items);
    }

    public function store(StoreTimeAdjustmentRequest $request): JsonResponse
    {
        $requestedTypes = collect($request->input('requested_entries'))->pluck('type');
        $duplicate = $request->user()->timeAdjustmentRequests()
            ->whereDate('work_date', $request->date('work_date'))->where('status', 'pending')->get()
            ->contains(fn ($item) => collect($item->requested_entries)->pluck('type')->intersect($requestedTypes)->isNotEmpty());
        abort_if($duplicate, 422, 'Já existe uma solicitação pendente para este tipo de batida nesta data.');

        $item = $request->user()->timeAdjustmentRequests()->create([...$request->validated(), 'status' => 'pending']);

        return response()->json(['message' => 'Ajuste enviado para aprovação do gestor.', 'data' => $this->resource($item)], 201);
    }

    public function show(Request $request, TimeAdjustmentRequest $adjustment): JsonResponse
    {
        abort_unless($adjustment->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->resource($adjustment)]);
    }

    private function resource(TimeAdjustmentRequest $item): array
    {
        return [
            'id' => $item->id,
            'work_date' => $item->work_date->format('Y-m-d'),
            'requested_entries' => $item->requested_entries,
            'reason' => $item->reason,
            'status' => $item->status,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}
