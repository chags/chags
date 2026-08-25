<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\StoreHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;

class HolidayController extends Controller
{
    public function store(StoreHolidayRequest $request): JsonResponse
    {
        Holiday::query()->create([
            ...$request->validated(),
            'state' => $request->filled('state') ? strtoupper($request->string('state')->toString()) : null,
            'city' => $request->string('city')->trim()->toString() ?: null,
            'active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Feriado cadastrado com sucesso.'], 201);
    }
}
