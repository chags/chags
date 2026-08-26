<?php

namespace App\Services\MobileApi;

use App\Contracts\WhatsAppMessageSender;
use Illuminate\Support\Str;

class FakeWhatsAppMessageSender implements WhatsAppMessageSender
{
    /** @var array<string, string> */
    private array $codes = [];

    public function sendCode(string $phone, string $code): string
    {
        $this->codes[$phone] = $code;

        return 'fake-'.Str::ulid();
    }

    public function codeFor(string $phone): ?string
    {
        return $this->codes[$phone] ?? null;
    }
}
