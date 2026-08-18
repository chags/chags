<?php

namespace App\Services\Addresses;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CepLookupService
{
    /** @return array{postal_code: string, address: string, district: string, city: string, state: string} */
    public function lookup(string $cep): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(config('services.brasilapi.timeout', 10))
                ->retry(2, 200, throw: false)
                ->get(rtrim(config('services.brasilapi.url'), '/').'/cep/v2/'.$cep);
        } catch (ConnectionException) {
            throw new RuntimeException('O serviço de consulta de CEP está indisponível no momento.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException('CEP não encontrado. Confira os números informados.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('O limite temporário de consultas de CEP foi atingido.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível consultar o CEP no momento.');
        }

        return [
            'postal_code' => (string) $response->json('cep', $cep),
            'address' => (string) $response->json('street', ''),
            'district' => (string) $response->json('neighborhood', ''),
            'city' => (string) $response->json('city', ''),
            'state' => (string) $response->json('state', ''),
        ];
    }
}
