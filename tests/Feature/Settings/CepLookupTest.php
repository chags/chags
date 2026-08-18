<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('an authenticated user can look up an address by cep', function () {
    Http::fake([
        'https://brasilapi.com.br/api/cep/v2/*' => Http::response([
            'cep' => '01001000',
            'state' => 'SP',
            'city' => 'São Paulo',
            'neighborhood' => 'Sé',
            'street' => 'Praça da Sé',
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/settings/profile/cep/01001000')
        ->assertOk()
        ->assertJsonPath('address.address', 'Praça da Sé')
        ->assertJsonPath('address.district', 'Sé')
        ->assertJsonPath('address.city', 'São Paulo')
        ->assertJsonPath('address.state', 'SP');
});

test('a guest cannot look up an address by cep', function () {
    $this->getJson('/settings/profile/cep/01001000')->assertUnauthorized();
});
