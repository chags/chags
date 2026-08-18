<?php

namespace App\Http\Controllers;

use App\Enums\CompanyUnitType;
use App\Models\Company;
use App\Models\MailSetting;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $company = Company::query()
            ->where('unit_type', CompanyUnitType::Headquarters->value)
            ->where('active', true)
            ->first();

        return Inertia::render('welcome', [
            'company' => $company ? [
                'name' => $company->name,
                'tradeName' => $company->trade_name ?: $company->name,
                'cnpj' => $company->cnpj,
                'logoUrl' => $company->logo_url,
                'address' => collect([
                    $company->address,
                    $company->address_number,
                    $company->address_complement,
                    $company->district,
                    $company->city.'/'.$company->state,
                    $company->postal_code,
                ])->filter()->join(', '),
            ] : null,
            'contactEmail' => MailSetting::query()->value('from_address'),
        ]);
    }
}
