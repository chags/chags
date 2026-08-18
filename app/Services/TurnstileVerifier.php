<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    /**
     * @throws ConnectionException
     */
    public function verify(string $token, string $secretKey, ?string $ipAddress = null): bool
    {
        $response = Http::asForm()
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $ipAddress,
            ]);

        if (! $response->successful()) {
            return false;
        }

        $result = $response->json();

        if (($result['success'] ?? false) !== true) {
            return false;
        }

        $usingOfficialLocalTestKey = app()->environment('local')
            && hash_equals(
                (string) config('services.turnstile.local_secret_key'),
                $secretKey,
            );

        return $usingOfficialLocalTestKey
            || ($result['action'] ?? null) === 'career_application';
    }
}
