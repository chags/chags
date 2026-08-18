<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('an hr professional can access the hr dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('rh-analista');

    $this->actingAs($user)
        ->get('/hr')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hr/dashboard')
            ->where('metrics.openJobs', 0)
            ->where('metrics.activeApplications', 0)
            ->where('metrics.activeEmployees', 0)
            ->where('metrics.departments', 0)
            ->has('recentJobs', 0));
});

test('a collaborator cannot access the hr dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('colaborador');

    $this->actingAs($user)->get('/hr')->assertForbidden();
});
