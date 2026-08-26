<?php

namespace App\Services\MobileApi;

use App\Models\InAppMessage;
use App\Models\User;
use App\Models\WhatsAppUnlockChallenge;
use App\Services\InAppMessageService;

class AppUnlockNotificationService
{
    public function __construct(private readonly InAppMessageService $messages) {}

    public function send(User $user, WhatsAppUnlockChallenge $challenge, string $code): void
    {
        $message = InAppMessage::query()->firstOrCreate(
            [
                'source_type' => $challenge->getMorphClass(),
                'source_id' => $challenge->getKey(),
            ],
            [
                'type' => 'app_unlock_code',
                'status' => 'draft',
                'title' => 'Código de acesso ao aplicativo',
                'body' => 'Use este código para liberar o aplicativo Chags Ponto.',
                'sensitive_payload' => ['code' => $code],
                'audience' => 'user',
                'expires_at' => $challenge->expires_at,
            ],
        );

        $this->messages->publish($message, [$user->id]);
    }
}
