<?php

namespace App\Http\Controllers\System;

use App\Data\Images\ImageProcessingOptions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Images\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CompanyLogoController extends Controller
{
    public function store(Request $request, Company $company, ImageProcessor $processor): JsonResponse
    {
        abort_unless($request->user()->can('system.settings.company.update'), 403);

        $request->validate([
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=36,min_height=36'],
        ]);

        $image = $processor->process($request->file('logo'), ImageProcessingOptions::logo());
        $path = "companies/{$company->id}/logos/{$image->filename}";
        $disk = Storage::disk('public');
        $oldLogo = $company->logo;

        try {
            if (! $disk->put($path, $image->contents)) {
                throw new \RuntimeException('Não foi possível armazenar a logomarca.');
            }

            $company->update(['logo' => $path]);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        if ($oldLogo) {
            $disk->delete($oldLogo);
        }

        return response()->json([
            'message' => 'Logomarca atualizada com sucesso.',
            'logo' => [
                'url' => '/storage/'.ltrim($path, '/'),
                'updated_at' => $company->updated_at?->toIso8601String(),
            ],
        ], $oldLogo ? 200 : 201);
    }
}
