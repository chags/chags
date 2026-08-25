<?php

namespace App\Http\Controllers\TimeManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeManagement\StoreMedicalCertificateRequest;
use App\Models\AbsenceJustification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalCertificateController extends Controller
{
    public function store(StoreMedicalCertificateRequest $request): JsonResponse
    {
        $document = $request->file('document');
        $path = $document->store("medical-certificates/{$request->user()->id}", 'local');

        $request->user()->absenceJustifications()->create([
            ...$request->safe()->except('document'),
            'type' => 'medical_certificate',
            'document_path' => $path,
            'original_filename' => $document->getClientOriginalName(),
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Atestado enviado para análise.', 'status' => 'pending'], 201);
    }

    public function download(Request $request, AbsenceJustification $justification): StreamedResponse
    {
        abort_unless($this->canView($request->user(), $justification), 403);
        abort_unless(Storage::disk('local')->exists($justification->document_path), 404);

        return Storage::disk('local')->download($justification->document_path, $justification->original_filename);
    }

    private function canView(User $viewer, AbsenceJustification $justification): bool
    {
        if ($viewer->id === $justification->user_id) {
            return true;
        }

        if ($viewer->hasRole('super-admin') || $viewer->can('time-records.manage')) {
            return true;
        }

        return $viewer->can('medical-certificates.review')
            && $justification->user()->whereHas(
                'employeeProfile',
                fn (Builder $profile) => $profile->where('manager_id', $viewer->id),
            )->exists();
    }
}
