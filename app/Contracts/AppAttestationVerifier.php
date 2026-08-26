<?php

namespace App\Contracts;

interface AppAttestationVerifier
{
    /** @return array{valid: bool, status: string} */
    public function verify(string $provider, string $token, string $nonce): array;
}
