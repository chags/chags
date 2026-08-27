<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkSchedule;
use App\Models\Holiday;
use App\Models\HourBankTransaction;
use App\Models\Position;
use App\Models\TimeAdjustmentRequest;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\VacationPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('the dashboard uses the business date and displays todays punches', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-21 01:00:00', 'UTC'));
    $user = User::factory()->create(['tracks_time' => true]);
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
    TimeEntry::query()->create([
        'user_id' => $user->id,
        'recorded_at' => '2026-08-21 00:30:00',
        'type' => 'clock_in',
        'source' => 'web',
    ]);

    $this->actingAs($user)
        ->get('/virtual-office')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('today.date', '2026-08-20')
            ->where('today.schedule.start', '08:00')
            ->where('today.entries.0.type', 'clock_in')
            ->where('today.entries.0.time', '21:30'));
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

test('a manager sees only time adjustments from direct reports', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');

    $directReport = User::factory()->create(['tracks_time' => true]);
    $directReport->assignRole('colaborador');
    createEmployee($directReport)->update(['manager_id' => $manager->id]);

    $otherEmployee = User::factory()->create(['tracks_time' => true]);
    $otherEmployee->assignRole('colaborador');
    createEmployee($otherEmployee);

    TimeAdjustmentRequest::query()->create([
        'user_id' => $directReport->id,
        'work_date' => '2026-08-19',
        'requested_entries' => [['type' => 'clock_out', 'time' => '17:00']],
        'reason' => 'Esqueci de registrar a saída no horário correto.',
        'status' => 'pending',
    ]);
    TimeAdjustmentRequest::query()->create([
        'user_id' => $otherEmployee->id,
        'work_date' => '2026-08-19',
        'requested_entries' => [['type' => 'clock_out', 'time' => '18:00']],
        'reason' => 'Solicitação pertencente a outro responsável.',
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->get('/personnel/time-approvals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('personnel/time-approvals/index')
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.employee.id', $directReport->id)
            ->has('employeesWithPending', 1)
            ->where('employeesWithPending.0.id', $directReport->id)
            ->where('employeesWithPending.0.pendingCount', 1));
});

test('a manager can open the current time card of a direct report', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $directReport = User::factory()->create(['tracks_time' => true]);
    $directReport->assignRole('colaborador');
    createEmployee($directReport)->update(['manager_id' => $manager->id]);
    $outsideTeam = User::factory()->create(['tracks_time' => true]);
    $outsideTeam->assignRole('colaborador');
    createEmployee($outsideTeam);

    $this->actingAs($manager)
        ->get("/personnel/time-approvals/employees/{$directReport->id}/time-card")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('virtual-office/time-card/index')
            ->where('employeeName', $directReport->name)
            ->where('managedView', true)
            ->where('canRequestAdjustment', false));

    $this->actingAs($manager)
        ->get("/personnel/time-approvals/employees/{$outsideTeam->id}/time-card")
        ->assertForbidden();
});

test('a manager can approve a direct report manual time adjustment', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    $adjustment = TimeAdjustmentRequest::query()->create([
        'user_id' => $employee->id,
        'work_date' => '2026-08-19',
        'requested_entries' => [['type' => 'clock_out', 'time' => '17:00']],
        'reason' => 'Esqueci de registrar a saída no horário correto.',
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->patchJson("/personnel/time-approvals/{$adjustment->id}", [
            'decision' => 'approve',
            'notes' => 'Horário confirmado com a equipe.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    $adjustment->refresh();
    expect($adjustment->status)->toBe('approved')
        ->and($adjustment->reviewed_by)->toBe($manager->id)
        ->and($adjustment->timeEntries)->toHaveCount(1)
        ->and($adjustment->timeEntries->first()->source)->toBe('manual')
        ->and($adjustment->timeEntries->first()->recorded_at->setTimezone(config('app.business_timezone'))->format('Y-m-d H:i'))->toBe('2026-08-19 17:00');
});

test('a manager must explain a rejection and no time entry is created', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    $adjustment = TimeAdjustmentRequest::query()->create([
        'user_id' => $employee->id,
        'work_date' => '2026-08-19',
        'requested_entries' => [['type' => 'clock_in', 'time' => '07:00']],
        'reason' => 'Solicito a inclusão manual da entrada informada.',
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->patchJson("/personnel/time-approvals/{$adjustment->id}", ['decision' => 'reject'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('notes');

    $this->actingAs($manager)
        ->patchJson("/personnel/time-approvals/{$adjustment->id}", [
            'decision' => 'reject',
            'notes' => 'O horário informado não foi confirmado.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'cancelled');

    expect($adjustment->refresh()->status)->toBe('cancelled')
        ->and($adjustment->review_notes)->toBe('O horário informado não foi confirmado.')
        ->and($adjustment->timeEntries()->exists())->toBeFalse();
});

test('a manager cannot review an employee outside their team', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee);
    $adjustment = TimeAdjustmentRequest::query()->create([
        'user_id' => $employee->id,
        'work_date' => '2026-08-19',
        'requested_entries' => [['type' => 'clock_in', 'time' => '08:00']],
        'reason' => 'Solicitação fora da equipe deste gestor.',
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->patchJson("/personnel/time-approvals/{$adjustment->id}", [
            'decision' => 'approve',
        ])
        ->assertForbidden();

    expect($adjustment->refresh()->status)->toBe('pending');
});

test('a manager can create an individual schedule exception without changing the group', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->postJson('/personnel/work-schedule-exceptions', [
            'user_id' => $employee->id,
            'work_date' => '2026-08-21',
            'type' => 'custom_schedule',
            'start_time' => '07:00',
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'end_time' => '16:00',
            'reason' => 'Jornada antecipada autorizada para este colaborador.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('work_schedule_exceptions', [
        'user_id' => $employee->id,
        'work_date' => '2026-08-21 00:00:00',
        'type' => 'custom_schedule',
        'expected_minutes' => 480,
        'created_by' => $manager->id,
    ]);
});

test('a manager can grant hour bank leave and debit the scheduled day', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    EmployeeWorkSchedule::query()->create([
        'user_id' => $employee->id,
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
    HourBankTransaction::query()->create([
        'user_id' => $employee->id,
        'work_date' => '2026-08-20',
        'minutes' => 480,
        'type' => 'worked',
    ]);

    $this->actingAs($manager)
        ->postJson('/personnel/work-schedule-exceptions', [
            'user_id' => $employee->id,
            'work_date' => '2026-08-21',
            'type' => 'hour_bank_leave',
            'reason' => 'Folga compensatória combinada com o colaborador.',
        ])
        ->assertCreated();

    expect($employee->hourBankTransactions()->sum('minutes'))->toBe(0);
    $this->assertDatabaseHas('hour_bank_transactions', [
        'user_id' => $employee->id,
        'work_date' => '2026-08-21 00:00:00',
        'minutes' => -480,
        'type' => 'leave',
    ]);

    $this->actingAs($employee)
        ->get('/virtual-office/time-card?month=2026-08')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeCard.days.20.occurrence', 'hour_bank_leave')
            ->where('timeCard.days.20.expectedMinutes', 0)
            ->where('timeCard.currentBalanceMinutes', 0));
});

test('hour bank leave is rejected when the employee balance is insufficient', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    EmployeeWorkSchedule::query()->create([
        'user_id' => $employee->id,
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

    $this->actingAs($manager)
        ->postJson('/personnel/work-schedule-exceptions', [
            'user_id' => $employee->id,
            'work_date' => '2026-08-21',
            'type' => 'hour_bank_leave',
            'reason' => 'Folga compensatória sem saldo suficiente disponível.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');

    $this->assertDatabaseMissing('work_schedule_exceptions', [
        'user_id' => $employee->id,
        'work_date' => '2026-08-21 00:00:00',
    ]);
});

test('automatic punches are approved inside the window and cancelled outside it', function () {
    $user = User::factory()->create(['tracks_time' => true]);
    $user->assignRole('colaborador');
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

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 07:00:00', config('app.business_timezone')));
    $this->actingAs($user)
        ->postJson('/virtual-office/time-punch')
        ->assertCreated()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('reason', 'outside_window');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 08:05:00', config('app.business_timezone')));
    $this->actingAs($user)
        ->postJson('/virtual-office/time-punch')
        ->assertCreated()
        ->assertJsonPath('status', 'approved');

    $this->assertDatabaseHas('time_entries', [
        'user_id' => $user->id,
        'recorded_at' => '2026-08-20 10:00:00',
        'status' => 'cancelled',
        'reason' => 'outside_window',
    ]);
});

test('a holiday removes expected hours and sends punches for approval', function () {
    $manager = User::factory()->create();
    $manager->assignRole('dp-analista');
    $user = User::factory()->create(['tracks_time' => true]);
    $user->assignRole('colaborador');
    $profile = createEmployee($user);
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
    Holiday::query()->create([
        'company_id' => $profile->company_id,
        'name' => 'Feriado da unidade',
        'holiday_date' => '2026-08-20',
        'scope' => 'company',
        'active' => true,
        'created_by' => $manager->id,
    ]);

    CarbonImmutable::setTestNow('2026-08-20 08:00:00');
    $this->actingAs($user)
        ->postJson('/virtual-office/time-punch')
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('reason', 'holiday');

    $this->actingAs($user)
        ->get('/virtual-office/time-card?month=2026-08')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeCard.days.19.occurrence', 'holiday')
            ->where('timeCard.days.19.expectedMinutes', 0)
            ->where('timeCard.days.19.holiday.name', 'Feriado da unidade'));
});

test('a medical certificate is stored privately and can be approved to excuse the day', function () {
    Storage::fake('local');
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    EmployeeWorkSchedule::query()->create([
        'user_id' => $employee->id,
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

    $this->actingAs($employee)
        ->postJson('/virtual-office/medical-certificates', [
            'type' => 'medical_certificate',
            'starts_on' => '2026-08-19',
            'ends_on' => '2026-08-20',
            'reason' => 'Ausência médica durante toda a jornada de trabalho.',
            'document' => UploadedFile::fake()->create('atestado.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending');

    $certificate = $employee->absenceJustifications()->sole();
    Storage::disk('local')->assertExists($certificate->document_path);

    $this->actingAs($manager)
        ->get('/personnel/medical-certificates?status=pending')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('personnel/medical-certificates/index')
            ->where('documents.data.0.employee', $employee->name)
            ->where('documents.data.0.status', 'pending'));

    $this->actingAs($manager)
        ->patchJson("/personnel/medical-certificates/{$certificate->id}", [
            'decision' => 'approve',
            'notes' => 'Documento conferido.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    $this->actingAs($employee)
        ->get('/virtual-office/time-card?month=2026-08')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeCard.days.18.occurrence', 'medical_leave')
            ->where('timeCard.days.18.excusedMinutes', 480)
            ->where('timeCard.days.18.expectedMinutes', 480)
            ->where('timeCard.days.18.workedMinutes', 480)
            ->where('timeCard.days.18.entries.0.source', 'medical_certificate')
            ->where('timeCard.days.19.occurrence', 'medical_leave')
            ->where('timeCard.days.19.excusedMinutes', 480)
            ->where('timeCard.days.19.expectedMinutes', 480)
            ->where('timeCard.days.19.workedMinutes', 480));

    expect($employee->hourBankTransactions()->sum('minutes'))->toBe(0);
    expect($certificate->timeEntries()->count())->toBe(8);
});

test('an absence declaration requires a same-day hourly interval', function () {
    Storage::fake('local');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee);

    $this->actingAs($employee)
        ->postJson('/virtual-office/medical-certificates', [
            'type' => 'absence_declaration',
            'starts_on' => '2026-08-19',
            'ends_on' => '2026-08-19',
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'reason' => 'Comparecimento a consulta durante parte da jornada.',
            'document' => UploadedFile::fake()->create('declaracao.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated();

    $this->assertDatabaseHas('absence_justifications', [
        'user_id' => $employee->id,
        'type' => 'absence_declaration',
        'starts_on' => '2026-08-19 00:00:00',
        'ends_on' => '2026-08-19 00:00:00',
        'starts_at' => '09:00',
        'ends_at' => '11:00',
    ]);

    $this->actingAs($employee)
        ->postJson('/virtual-office/medical-certificates', [
            'type' => 'absence_declaration',
            'starts_on' => '2026-08-19',
            'ends_on' => '2026-08-20',
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'reason' => 'Declaração inválida abrangendo mais de um dia.',
            'document' => UploadedFile::fake()->create('declaracao.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_on');
});

test('a manager approval credits a complete overtime pair to the hour bank', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    TimeEntry::query()->create([
        'user_id' => $employee->id,
        'recorded_at' => '2026-08-19 20:00:00',
        'type' => 'clock_out',
        'source' => 'web',
        'status' => 'approved',
        'created_by' => $employee->id,
    ]);
    $adjustment = TimeAdjustmentRequest::query()->create([
        'user_id' => $employee->id,
        'work_date' => '2026-08-19',
        'requested_entries' => [
            ['type' => 'overtime_start', 'time' => '18:00'],
            ['type' => 'overtime_end', 'time' => '20:00'],
        ],
        'reason' => 'Período extraordinário realizado após o expediente regular.',
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->patchJson("/personnel/time-approvals/{$adjustment->id}", [
            'decision' => 'approve',
        ])
        ->assertOk();

    $this->assertDatabaseHas('hour_bank_transactions', [
        'user_id' => $employee->id,
        'work_date' => '2026-08-19 00:00:00',
        'minutes' => 120,
        'type' => 'overtime',
        'time_adjustment_request_id' => $adjustment->id,
    ]);
});

test('approving two direct overtime punches credits the hour bank after the complete pair', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);

    foreach ([
        ['type' => 'clock_out', 'recorded_at' => '2026-08-19 20:00:00', 'status' => 'approved'],
        ['type' => 'overtime_start', 'recorded_at' => '2026-08-19 20:30:00', 'status' => 'pending'],
        ['type' => 'overtime_end', 'recorded_at' => '2026-08-19 22:00:00', 'status' => 'pending'],
    ] as $data) {
        TimeEntry::query()->create([
            'user_id' => $employee->id,
            'source' => 'manual',
            'created_by' => $employee->id,
            ...$data,
        ]);
    }

    $start = $employee->timeEntries()->where('type', 'overtime_start')->sole();
    $end = $employee->timeEntries()->where('type', 'overtime_end')->sole();

    $this->actingAs($manager)
        ->patchJson("/personnel/time-entries/{$start->id}", ['decision' => 'approve'])
        ->assertOk();
    expect($employee->hourBankTransactions()->count())->toBe(0);

    $this->actingAs($manager)
        ->patchJson("/personnel/time-entries/{$end->id}", ['decision' => 'approve'])
        ->assertOk();

    $this->assertDatabaseHas('hour_bank_transactions', [
        'user_id' => $employee->id,
        'work_date' => '2026-08-19 00:00:00',
        'minutes' => 90,
        'type' => 'overtime',
    ]);
});

test('overtime approval rejects a period longer than two hours', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    TimeEntry::query()->create([
        'user_id' => $employee->id,
        'recorded_at' => '2026-08-19 20:00:00',
        'type' => 'clock_out',
        'source' => 'web',
        'status' => 'approved',
        'created_by' => $employee->id,
    ]);
    $adjustment = TimeAdjustmentRequest::query()->create([
        'user_id' => $employee->id,
        'work_date' => '2026-08-19',
        'requested_entries' => [
            ['type' => 'overtime_start', 'time' => '18:00'],
            ['type' => 'overtime_end', 'time' => '20:01'],
        ],
        'reason' => 'Período extraordinário acima do limite diário permitido.',
        'status' => 'pending',
    ]);

    $this->actingAs($manager)
        ->patchJson("/personnel/time-approvals/{$adjustment->id}", ['decision' => 'approve'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('requested_entries');

    expect($adjustment->refresh()->status)->toBe('pending');
    $this->assertDatabaseMissing('hour_bank_transactions', [
        'time_adjustment_request_id' => $adjustment->id,
    ]);
});

test('a manager can approve a pending holiday punch from a direct report', function () {
    $manager = User::factory()->create();
    $manager->assignRole('gestor');
    $employee = User::factory()->create(['tracks_time' => true]);
    $employee->assignRole('colaborador');
    createEmployee($employee)->update(['manager_id' => $manager->id]);
    $entry = TimeEntry::query()->create([
        'user_id' => $employee->id,
        'recorded_at' => '2026-08-20 08:00:00',
        'type' => 'clock_in',
        'source' => 'web',
        'status' => 'pending',
        'reason' => 'holiday',
        'created_by' => $employee->id,
    ]);

    $this->actingAs($manager)
        ->patchJson("/personnel/time-entries/{$entry->id}", [
            'decision' => 'approve',
            'notes' => 'Trabalho no feriado confirmado.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    expect($entry->refresh()->status)->toBe('approved')
        ->and($entry->reviewed_by)->toBe($manager->id);
});

test('time punches are displayed and grouped in the business timezone', function () {
    $user = User::factory()->create(['tracks_time' => true]);
    $user->assignRole('colaborador');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25T01:08:00+00:00'));
    TimeEntry::query()->create([
        'user_id' => $user->id,
        'recorded_at' => '2026-08-25 01:00:53+00',
        'type' => 'clock_in',
        'source' => 'web',
        'status' => 'cancelled',
        'reason' => 'outside_window',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson('/virtual-office/time-punch')
        ->assertOk()
        ->assertJsonPath('entries.0.time', '22:00');

    $this->actingAs($user)
        ->get('/virtual-office/time-card?month=2026-08')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeCard.days.23.date', '2026-08-24')
            ->where('timeCard.days.23.entries.0.time', '22:00'));
});
