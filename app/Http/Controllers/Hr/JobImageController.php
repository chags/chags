<?php

namespace App\Http\Controllers\Hr;

use App\Data\Images\ImageProcessingOptions;
use App\Http\Controllers\Controller;
use App\Models\HrAuditEvent;
use App\Models\Job;
use App\Services\Images\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class JobImageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Job $job, ImageProcessor $processor): JsonResponse
    {
        Gate::authorize('update', $job);
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=400,min_height=400,ratio=1/1']]);
        $image = $processor->process($request->file('image'), new ImageProcessingOptions(quality: 84, maxWidth: 1200, maxHeight: 1200, maxBytes: 4 * 1024 * 1024, maxPixels: config('images.max_pixels')));
        $path = "jobs/{$job->id}/images/{$image->filename}";
        $disk = Storage::disk('public');
        $oldImage = $job->image;
        try {
            abort_unless($disk->put($path, $image->contents), 500, 'Não foi possível armazenar a imagem.');
            $job->update(['image' => $path]);
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
        if ($oldImage) {
            $disk->delete($oldImage);
        }
        HrAuditEvent::query()->create(['actor_id' => $request->user()->id, 'impersonator_id' => $request->session()->get('impersonation.original_user_id'), 'event' => 'job.image.updated', 'auditable_type' => $job->getMorphClass(), 'auditable_id' => $job->id, 'old_values' => ['image' => $oldImage], 'new_values' => ['image' => $path], 'ip_address' => $request->ip()]);

        return response()->json(['message' => 'Imagem da vaga atualizada com sucesso.', 'image_url' => $job->image_url], $oldImage ? 200 : 201);
    }
}
