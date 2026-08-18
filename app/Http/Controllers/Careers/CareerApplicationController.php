<?php

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\TurnstileSetting;
use App\Models\User;
use App\Services\ResumeExtractionService;
use App\Services\TurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CareerApplicationController extends Controller
{
    public function __invoke(Request $request, string $slug, TurnstileVerifier $turnstileVerifier, ResumeExtractionService $resumeExtraction): RedirectResponse
    {
        $job = Job::query()->where('slug', $slug)->where('status', 'published')->where(fn ($query) => $query->whereNull('closes_at')->orWhereDate('closes_at', '>=', today()))->firstOrFail();
        $turnstile = TurnstileSetting::query()->first();
        $siteKey = app()->environment('local') ? config('services.turnstile.local_site_key') : $turnstile?->site_key;
        $secretKey = app()->environment('local') ? config('services.turnstile.local_secret_key') : $turnstile?->secret_key;
        $turnstileEnabled = $turnstile?->enabled === true && filled($siteKey) && filled($secretKey);
        $data = $request->validate(
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['required', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:255'], 'state' => ['nullable', 'string', 'size:2'], 'password' => ['required', 'string', 'min:8', 'confirmed'], 'cover_letter' => ['nullable', 'string', 'max:10000'], 'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'], 'privacy_consent' => ['accepted'], 'turnstile_token' => [$turnstileEnabled ? 'required' : 'nullable', 'string', 'max:2048']],
            [
                'required' => 'O campo :attribute é obrigatório.',
                'email.email' => 'Informe um endereço de e-mail válido.',
                'email.unique' => 'Este e-mail já possui uma conta cadastrada.',
                'password.min' => 'A senha deve ter pelo menos :min caracteres.',
                'password.confirmed' => 'A confirmação da senha não corresponde.',
                'state.size' => 'Selecione um estado válido.',
                'resume.file' => 'O currículo deve ser um arquivo válido.',
                'resume.mimes' => 'O currículo deve estar no formato PDF, DOC ou DOCX.',
                'resume.max' => 'O currículo não pode ser maior que 5 MB.',
                'privacy_consent.accepted' => 'Você precisa aceitar o Aviso de Privacidade e LGPD.',
            ],
            [
                'name' => 'nome completo',
                'email' => 'e-mail',
                'phone' => 'telefone',
                'password' => 'senha',
                'resume' => 'currículo',
                'privacy_consent' => 'consentimento de privacidade',
                'turnstile_token' => 'verificação de segurança',
            ],
        );

        if ($turnstileEnabled) {
            try {
                $validToken = $turnstileVerifier->verify(
                    $data['turnstile_token'],
                    $secretKey,
                    $request->ip(),
                );
            } catch (ConnectionException) {
                throw ValidationException::withMessages([
                    'turnstile_token' => 'Não foi possível validar a segurança agora. Tente novamente em instantes.',
                ]);
            }

            if (! $validToken) {
                throw ValidationException::withMessages([
                    'turnstile_token' => 'A verificação de segurança expirou ou não foi aceita. Tente novamente.',
                ]);
            }
        }

        $resumePath = null;
        $application = null;
        try {
            DB::transaction(function () use ($data, $job, $request, &$resumePath, &$application): void {
                $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'phone' => preg_replace('/\D/', '', $data['phone']), 'password' => $data['password'], 'workos_id' => 'candidate-'.Str::uuid(), 'avatar' => '', 'email_verified_at' => now()]);
                $user->syncRoles(['candidato']);
                CandidateProfile::query()->create(['user_id' => $user->id, 'city' => $data['city'] ?? null, 'state' => isset($data['state']) ? strtoupper($data['state']) : null]);
                $resume = $request->file('resume');
                $resumePath = $resume->storeAs("candidate-resumes/{$user->id}", Str::uuid().'.'.strtolower($resume->getClientOriginalExtension()), 'local');
                if (! $resumePath) {
                    throw new RuntimeException('Não foi possível armazenar o currículo.');
                }
                $application = Application::query()->create(['job_id' => $job->id, 'candidate_id' => $user->id, 'status' => 'active', 'source' => 'site', 'cover_letter' => $data['cover_letter'] ?? null, 'resume_path' => $resumePath, 'resume_original_name' => $resume->getClientOriginalName(), 'resume_mime_type' => $resume->getMimeType(), 'resume_size' => $resume->getSize(), 'privacy_consent_at' => now(), 'privacy_consent_version' => '2026-08-11', 'privacy_consent_ip' => $request->ip(), 'applied_at' => now()]);
            });
        } catch (Throwable $exception) {
            if ($resumePath) {
                Storage::disk('local')->delete($resumePath);
            }
            throw $exception;
        }

        if ($application) {
            $resumeExtraction->extract($application);
        }

        return back()->with('success', 'Candidatura enviada com sucesso.');
    }
}
