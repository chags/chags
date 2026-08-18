<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Addresses\CepLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CepLookupController extends Controller
{
    public function __invoke(string $cep, CepLookupService $service): JsonResponse
    {
        $cep = preg_replace('/\D/', '', $cep) ?? '';
        Validator::make(['cep' => $cep], ['cep' => ['required', 'digits:8']])->validate();

        try {
            return response()->json([
                'message' => 'Endereço encontrado.',
                'address' => $service->lookup($cep),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
