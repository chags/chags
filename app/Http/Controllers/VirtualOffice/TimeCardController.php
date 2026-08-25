<?php

namespace App\Http\Controllers\VirtualOffice;

use App\Http\Controllers\Controller;
use App\Services\VirtualOffice\TimeCardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeCardController extends Controller
{
    public function index(Request $request, TimeCardService $timeCardService): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : CarbonImmutable::now()->startOfMonth();
        abort_unless($request->user()->tracks_time, 403, 'Seu usuário não está configurado para registrar ponto.');

        return Inertia::render('virtual-office/time-card/index', [
            'timeCard' => $timeCardService->forMonth($request->user(), $month),
            'canRequestAdjustment' => $request->user()->can('time-records.request-adjustment'),
            'canSubmitMedicalCertificate' => $request->user()->can('medical-certificates.submit'),
        ]);
    }
}
