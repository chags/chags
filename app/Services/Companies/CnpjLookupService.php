<?php

namespace App\Services\Companies;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CnpjLookupService
{
    /** @return array<string, string> */
    public function lookup(string $cnpj): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(config('services.cnpja.timeout', 10))
                ->retry(2, 200, throw: false)
                ->get(rtrim(config('services.cnpja.url'), '/').'/office/'.$cnpj);
        } catch (ConnectionException) {
            throw new RuntimeException('O serviço de consulta de CNPJ está indisponível no momento.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException('CNPJ não encontrado na base pública.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('O limite temporário de consultas de CNPJ foi atingido. Tente novamente mais tarde.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível consultar o CNPJ no momento.');
        }

        $data = $response->json();
        $address = $data['address'] ?? [];

        return [
            'cnpj' => (string) ($data['taxId'] ?? $cnpj),
            'name' => (string) data_get($data, 'company.name', ''),
            'trade_name' => (string) ($data['alias'] ?? ''),
            'postal_code' => (string) ($address['zip'] ?? ''),
            'address' => (string) ($address['street'] ?? ''),
            'address_number' => (string) ($address['number'] ?? ''),
            'address_complement' => (string) ($address['details'] ?? ''),
            'district' => (string) ($address['district'] ?? ''),
            'city' => (string) ($address['city'] ?? ''),
            'state' => (string) ($address['state'] ?? ''),
        ];
    }
}
