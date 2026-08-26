<?php

namespace App\Services\MobileApi;

class DeviceSignatureVerifier
{
    public function verify(string $publicKey, string $nonce, string $signature): bool
    {
        $decoded = base64_decode(strtr($signature, '-_', '+/'), true);

        return $decoded !== false && openssl_verify($nonce, $decoded, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
