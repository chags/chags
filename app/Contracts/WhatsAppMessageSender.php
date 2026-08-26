<?php

namespace App\Contracts;

interface WhatsAppMessageSender
{
    public function sendCode(string $phone, string $code): string;
}
