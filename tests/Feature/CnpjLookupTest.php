<?php

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->seed(SuperAdminSeeder::class);
});

test('an administrator can look up a cnpj and receive normalized company fields', function () {
    Http::fake([
        'https://open.cnpja.com/office/*' => Http::response([
            'taxId' => '07526557011659',
            'alias' => 'Filial Manaus',
            'company' => ['name' => 'AMBEV S.A.'],
            'address' => [
                'street' => 'Avenida Constantino Nery',
                'number' => '2575',
                'details' => null,
                'district' => 'Flores',
                'city' => 'Manaus',
                'state' => 'AM',
                'zip' => '69058795',
            ],
        ]),
    ]);

    $user = User::query()->where('email', config('auth.super_admin.email'))->sole();

    $this->actingAs($user)
        ->getJson('/settings/system/companies/cnpj/07526557011659')
        ->assertOk()
        ->assertJsonPath('company.name', 'AMBEV S.A.')
        ->assertJsonPath('company.trade_name', 'Filial Manaus')
        ->assertJsonPath('company.city', 'Manaus')
        ->assertJsonPath('company.state', 'AM');

    Http::assertSentCount(1);
});

test('a user without permission cannot look up a cnpj', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/settings/system/companies/cnpj/07526557011659')
        ->assertForbidden();
});
