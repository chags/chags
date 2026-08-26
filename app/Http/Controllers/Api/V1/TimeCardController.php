<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\VirtualOffice\TimeCardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeCardController extends Controller
{
    public function __invoke(Request $request, TimeCardService $service): JsonResponse
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'], config('app.business_timezone'))
            : CarbonImmutable::now(config('app.business_timezone'))->startOfMonth();

        return response()->json(['data' => $service->forMonth($request->user(), $month)]);
    }
}
