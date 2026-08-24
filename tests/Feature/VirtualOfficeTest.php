<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkSchedule;
use App\Models\HourBankTransaction;
use App\Models\Position;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\VacationPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    CarbonImmutable::setTestNow('2026-08-20 12:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function createEmployee(User $user): EmployeeProfile
{
    $company = Company::query()->create([
        'unit_type' => 'headquarters',
        'unit_number' => fake()->unique()->numerify('####'),
        'unit_name' => 'Matriz',
        'name' => 'Empresa Teste Ltda',
        'trade_name' => 'Empresa Teste',
        'cnpj' => fake()->unique()->numerify('##############'),
        'address' => 'Rua Teste',
        'address_number' => '100',
        'district' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'postal_code' => '01001000',
        'active' => true,
    ]);
    $department = Department::query()->create([
        'company_id' => $company->id,
        'name' => 'Operações',
        'slug' => 'operacoes',
        'active' => true,
    ]);
    $position = Position::query()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'title' => 'Analista',
        'level' => 'mid',
        'code' => 'ANL-01',
        'active' => true,
    ]);

    return EmployeeProfile::query()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employee_number' => fake()->unique()->numerify('EMP-####'),
        'employment_status' => 'active',
        'hire_date' => '2025-01-10',
    ]);
}

test('a collaborator with an active employee profile can view the virtual office dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('colaborador');
    createEmployee($user);
    EmployeeWorkSchedule::query()->create([
        'user_id' => $user->id,
        'name' => 'Administrativa',
        'weekdays' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'break_start_time' => '12:00',
        'break_end_time' => '13:00',
        'end_time' => '17:00',
        'daily_minutes' => 480,
        'weekly_minutes' => 2400,
        'valid_from' => '2026-01-01',
        'active' => true,
    ]);
    VacationPeriod::query()->create([
        'user_id' => $user->id,
        'accrual_start' => '2025-01-10',
        'accrual_end' => '2026-01-09',
        'entitled_days' => 30,
        'used_days' => 10,
        'status' => 'available',
    ]);

    $this->actingAs($user)
        ->get('/virtual-office')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('virtual-office/dashboard')
            ->where('employee.name', $user->name)
            ->where('employee.department', 'Operações')
            ->where('vacation.availableDays', 20)
            ->where('pendingAdjustments', 0));
});

test('the time card calculates worked time and hour bank balance', function () {
    $user = User::factory()->create();
    $user->assignRole('colaborador');
    $user->update(['tracks_time' => true]);
    EmployeeWorkSchedule::query()->create([
        'user_id' => $user->id,
        'name' => 'Administrativa',
        'weekdays' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'break_start_time' => '12:00',
        'break_end_time' => '13:00',
        'end_time' => '17:00',
        'daily_minutes' => 480,
        'weekly_minutes' => 2400,
        'valid_from' => '2026-01-01',
        'active' => true,
    ]);

    foreach ([
        ['clock_in', '2026-08-03 08:00:00'],
        ['break_start', '2026-08-03 12:00:00'],
        ['break_end', '2026-08-03 13:00:00'],
        ['clock_out', '2026-08-03 17:00:00'],
    ] as [$type, $recordedAt]) {
        TimeEntry::query()->create([
            'user_id' => $user->id,
            'recorded_at' => $recordedAt,
            'type' => $type,
            'source' => 'import',
        ]);
    }

    HourBankTransaction::query()->create([
        'user_id' => $user->id,
        'work_date' => '2026-08-03',
        'minutes' => 30,
        'type' => 'worked',
    ]);

    $this->actingAs($user)
        ->get('/virtual-office/time-card?month=2026-08')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('virtual-office/time-card/index')
            ->where('timeCard.month', '2026-08')
            ->where('timeCard.workedMinutes', 480)
            ->where('timeCard.monthBalanceMinutes', 30)
            ->where('timeCard.currentBalanceMinutes', 30)
            ->where('timeCard.days.2.entries.0.type', 'clock_in'));
});

test('a time tracking user can register the four daily punches in order', function () {
    $user = User::factory()->create(['tracks_time' => true]);
    $user->assignRole('colaborador');

    $this->actingAs($user)
        ->getJson('/virtual-office/time-punch')
        ->assertOk()
        ->assertJsonPath('nextType', 'clock_in');

    foreach (['clock_in', 'break_start', 'break_end', 'clock_out'] as $index => $type) {
        CarbonImmutable::setTestNow('2026-08-20 '.(8 + ($index * 3)).':00:00');
        $this->actingAs($user)
            ->postJson('/virtual-office/time-punch')
            ->assertCreated()
            ->assertJsonPath('registeredType', $type);
    }

    $this->actingAs($user)
        ->postJson('/virtual-office/time-punch')
        ->assertUnprocessable();

    $this->assertDatabaseCount('time_entries', 4);
});

test('a collaborator can request a time adjustment only for their own profile', function () {
    $user = User::factory()->create();
    $user->assignRole('colaborador');
    $user->update(['tracks_time' => true]);
    $this->actingAs($user)->post('/virtual-office/time-adjustments', [
        'work_date' => '2026-08-19',
        'requested_entries' => [
            ['type' => 'clock_in', 'time' => '08:00'],
            ['type' => 'break_start', 'time' => '12:00'],
            ['type' => 'break_end', 'time' => '13:00'],
            ['type' => 'clock_out', 'time' => '17:00'],
        ],
        'reason' => 'O relógio não registrou corretamente a minha saída.',
    ])->assertRedirect();

    expect($user->timeAdjustmentRequests()->whereDate('work_date', '2026-08-19')->where('status', 'pending')->exists())
        ->toBeTrue();
});

test('a collaborator can request one overtime punch adjustment as pending', function () {
    $user = User::factory()->create(['tracks_time' => true]);
    $user->assignRole('colaborador');

    $this->actingAs($user)->postJson('/virtual-office/time-adjustments', [
        'work_date' => '2026-08-19',
        'requested_entries' => [
            ['type' => 'overtime_start', 'time' => '18:00'],
        ],
        'reason' => 'Esqueci de registrar o início da hora extra.',
    ])->assertCreated()->assertJsonPath('status', 'pending');

    $this->assertDatabaseHas('time_adjustment_requests', [
        'user_id' => $user->id,
        'work_date' => '2026-08-19 00:00:00',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get('/virtual-office/time-card?month=2026-08')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeCard.days.18.adjustmentStatus', 'pending')
            ->where('timeCard.days.18.pendingEntries.0.type', 'overtime_start')
            ->where('timeCard.days.18.pendingEntries.0.time', '18:00'));
});

test('an internal user without an employee profile can access the virtual office dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('colaborador');

    $this->actingAs($user)
        ->get('/virtual-office')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('virtual-office/dashboard')
            ->where('employee.name', $user->name)
            ->where('employee.employeeNumber', null)
            ->where('tracksTime', false));

    $this->actingAs($user)->get('/virtual-office/time-card')->assertForbidden();
});
