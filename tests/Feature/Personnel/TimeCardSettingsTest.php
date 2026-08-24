<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('department personnel can create a schedule group and assign a time tracking user', function () {
    $manager = User::factory()->create();
    $manager->assignRole('dp-analista');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');

    $this->actingAs($manager)->get('/personnel/time-card-settings')->assertOk()->assertInertia(fn (Assert $page) => $page->component('personnel/time-card-settings/index')->where('metrics.tracksTimeUsers', 1));

    $response = $this->actingAs($manager)->postJson('/personnel/time-card-settings/groups', [
        'name' => 'Comercial', 'description' => 'Segunda a sexta', 'schedule_type' => '5x2', 'weekly_minutes' => 2640,
        'entry_tolerance_minutes' => 5, 'daily_tolerance_minutes' => 10, 'operational_window_minutes' => 10,
        'daily_overtime_limit_minutes' => 120, 'requires_overtime_approval' => true, 'cycle_start_date' => null, 'active' => true,
        'days' => [['day_index' => 1, 'label' => 'Segunda', 'is_workday' => true, 'start_time' => '08:00', 'break_start_time' => '12:00', 'break_end_time' => '13:00', 'end_time' => '17:00', 'expected_minutes' => 480]],
    ])->assertCreated();

    $secondGroup = $this->actingAs($manager)->postJson('/personnel/time-card-settings/groups', [
        'name' => 'Comercial', 'description' => 'Escala de segunda a sábado', 'schedule_type' => '6x1', 'weekly_minutes' => 2640,
        'entry_tolerance_minutes' => 5, 'daily_tolerance_minutes' => 10, 'operational_window_minutes' => 10,
        'daily_overtime_limit_minutes' => 120, 'requires_overtime_approval' => true, 'cycle_start_date' => null, 'active' => true,
        'days' => [['day_index' => 1, 'label' => 'Segunda', 'is_workday' => true, 'start_time' => '08:00', 'break_start_time' => '12:00', 'break_end_time' => '13:00', 'end_time' => '17:00', 'expected_minutes' => 480]],
    ]);
    $secondGroup->assertCreated();

    $this->actingAs($manager)->putJson('/personnel/time-card-settings/groups/'.$secondGroup->json('id'), [
        'name' => 'Comercial 6x1', 'description' => 'Escala de segunda a sábado', 'schedule_type' => '6x1', 'weekly_minutes' => 2640,
        'entry_tolerance_minutes' => 5, 'daily_tolerance_minutes' => 10, 'operational_window_minutes' => 10,
        'daily_overtime_limit_minutes' => 120, 'requires_overtime_approval' => true, 'cycle_start_date' => null, 'active' => true,
        'days' => [['day_index' => 1, 'label' => 'Segunda', 'is_workday' => true, 'start_time' => '08:00:00', 'break_start_time' => '12:00:00', 'break_end_time' => '13:00:00', 'end_time' => '17:00:00', 'expected_minutes' => 480]],
    ])->assertOk();

    $this->actingAs($manager)->postJson('/personnel/time-card-settings/assignments', ['work_schedule_group_id' => $response->json('id'), 'user_id' => $employee->id, 'valid_from' => '2026-08-01'])->assertOk();
    $this->assertDatabaseHas('work_schedule_assignments', ['user_id' => $employee->id, 'work_schedule_group_id' => $response->json('id'), 'active' => true]);
});

test('a collaborator cannot manage time card settings', function () {
    $user = User::factory()->create();
    $user->assignRole('colaborador');
    $this->actingAs($user)->get('/personnel/time-card-settings')->assertForbidden();
});
