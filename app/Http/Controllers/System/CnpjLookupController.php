<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\Companies\CnpjLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CnpjLookupController extends Controller
{
    public function __invoke(string $cnpj, CnpjLookupService $service): JsonResponse
    {
        $cnpj = preg_replace('/\D/', '', $cnpj) ?? '';

        Validator::make(['cnpj' => $cnpj], [
            'cnpj' => ['required', 'digits:14'],
        ])->validate();

        try {
            return response()->json([
                'message' => 'Dados do CNPJ encontrados.',
                'company' => $service->lookup($cnpj),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
