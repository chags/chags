<?php

namespace App\Services\MobileApi;

use App\Contracts\WhatsAppMessageSender;
use App\Models\User;
use App\Models\WhatsAppUnlockChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppCodeService
{
    public function __construct(
        private readonly PhoneNormalizer $normalizer,
        private readonly WhatsAppMessageSender $sender,
        private readonly AppUnlockNotificationService $notifications,
    ) {}

    public function request(string $phone, string $installationId, Request $request): WhatsAppUnlockChallenge
    {
        $normalized = $this->normalizer->normalize($phone);
        $user = User::query()
            ->where('whatsapp_phone', $normalized)
            ->whereNotNull('whatsapp_phone_verified_at')
            ->first();

        // Until the external WhatsApp verification channel is enabled, a
        // profile phone can identify the recipient because the code itself is
        // delivered only inside that user's authenticated web panel.
        if (! $user && str_starts_with($normalized, '+55')) {
            $matches = User::query()
                ->where('phone', substr($normalized, 3))
                ->limit(2)
                ->get();

            $user = $matches->count() === 1 ? $matches->first() : null;
        }
        // Always generate and hash a code so registered and unknown numbers take
        // the same expensive path. Only the delivery step depends on the user.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = WhatsAppUnlockChallenge::query()->create([
            'user_id' => $user?->id,
            'phone_hash' => $this->fingerprint($normalized),
            'device_installation_id' => $installationId,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds(config('mobile-api.whatsapp.code_ttl_seconds')),
            'request_ip_hash' => $request->ip() ? $this->fingerprint($request->ip()) : null,
        ]);

        if ($user) {
            $this->notifications->send($user, $challenge, $code);

            try {
                $challenge->update(['provider_message_id' => $this->sender->sendCode($normalized, $code)]);
            } catch (\Throwable $exception) {
                Log::warning('Falha no envio externo do código de acesso.', [
                    'challenge_id' => $challenge->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $challenge;
    }

    public function verify(WhatsAppUnlockChallenge $challenge, string $installationId, string $code): User
    {
        if ($challenge->consumed_at || $challenge->expires_at->isPast() ||
            ! hash_equals($challenge->device_installation_id, $installationId) ||
            $challenge->attempts >= config('mobile-api.whatsapp.max_attempts')) {
            throw ValidationException::withMessages(['code' => 'Código inválido ou expirado.']);
        }

        $challenge->increment('attempts');

        if (! $challenge->user_id || ! $challenge->code_hash || ! Hash::check($code, $challenge->code_hash)) {
            throw ValidationException::withMessages(['code' => 'Código inválido ou expirado.']);
        }

        $challenge->update(['consumed_at' => now()]);

        return $challenge->user()->firstOrFail();
    }

    private function fingerprint(string $value): string
    {
        return hash_hmac('sha256', Str::lower($value), config('app.key'));
    }
}
