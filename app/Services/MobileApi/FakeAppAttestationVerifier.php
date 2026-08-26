<?php

namespace App\Services\MobileApi;

use App\Contracts\AppAttestationVerifier;

class FakeAppAttestationVerifier implements AppAttestationVerifier
{
    public function verify(string $provider, string $token, string $nonce): array
    {
        return ['valid' => $token === 'valid-test-attestation', 'status' => $token === 'valid-test-attestation' ? 'verified' : 'failed'];
    }
}
