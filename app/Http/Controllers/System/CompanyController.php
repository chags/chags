<?php

namespace App\Http\Controllers\System;

use App\Enums\CompanyUnitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\CompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function store(CompanyRequest $request): JsonResponse
    {
        $company = DB::transaction(function () use ($request): Company {
            $this->guardHeadquartersRules($request->validated());

            return Company::query()->create($request->validated());
        });

        return response()->json([
            'message' => 'Unidade cadastrada com sucesso.',
            'company_id' => $company->id,
        ], 201);
    }

    public function update(CompanyRequest $request, Company $company): JsonResponse
    {
        DB::transaction(function () use ($request, $company): void {
            $data = $request->validated();

            if ($company->unit_type === CompanyUnitType::Headquarters) {
                if ($data['unit_type'] !== CompanyUnitType::Headquarters->value && $company->branches()->exists()) {
                    throw ValidationException::withMessages([
                        'unit_type' => 'A matriz não pode ser transformada em filial enquanto possuir filiais.',
                    ]);
                }

                if (! $data['active'] && $company->branches()->where('active', true)->exists()) {
                    throw ValidationException::withMessages([
                        'active' => 'A matriz não pode ser desativada enquanto possuir filiais ativas.',
                    ]);
                }
            }

            $this->guardHeadquartersRules($data, $company);
            $company->update($data);
        });

        return response()->json(['message' => 'Unidade atualizada com sucesso.']);
    }

    private function guardHeadquartersRules(array $data, ?Company $company = null): void
    {
        if ($data['unit_type'] === CompanyUnitType::Headquarters->value) {
            $exists = Company::query()
                ->where('unit_type', CompanyUnitType::Headquarters->value)
                ->when($company, fn ($query) => $query->whereKeyNot($company->getKey()))
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'unit_type' => 'Já existe uma matriz cadastrada.',
                ]);
            }
        }

        if ($company !== null && ($data['headquarters_id'] ?? null) === $company->getKey()) {
            throw ValidationException::withMessages([
                'headquarters_id' => 'Uma unidade não pode ser vinculada a ela mesma.',
            ]);
        }
    }
}
