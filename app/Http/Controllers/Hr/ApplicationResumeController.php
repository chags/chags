<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationResumeController extends Controller
{
    public function __invoke(Application $application): StreamedResponse
    {
        Gate::authorize('view', $application);
        abort_unless($application->resume_path && Storage::disk('local')->exists($application->resume_path), 404);

        return Storage::disk('local')->download(
            $application->resume_path,
            $application->resume_original_name ?: 'curriculo',
        );
    }
}
