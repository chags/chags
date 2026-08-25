<?php

use App\Http\Controllers\Candidate\CandidateApplicationController;
use App\Http\Controllers\Candidate\CandidateAuthController;
use App\Http\Controllers\Candidate\CandidatePortalController;
use App\Http\Controllers\Candidate\DiscAssessmentController;
use App\Http\Controllers\Candidate\InterviewScheduleController as CandidateInterviewScheduleController;
use App\Http\Controllers\Careers\CareerApplicationController;
use App\Http\Controllers\Careers\CareerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Hr\ApplicationController;
use App\Http\Controllers\Hr\ApplicationResumeController;
use App\Http\Controllers\Hr\DepartmentController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\InterviewScheduleController;
use App\Http\Controllers\Hr\JobController;
use App\Http\Controllers\Hr\JobImageController;
use App\Http\Controllers\Hr\PositionController;
use App\Http\Controllers\Personnel\TimeCardSettingsController;
use App\Http\Controllers\TimeManagement\HolidayController;
use App\Http\Controllers\TimeManagement\MedicalCertificateController;
use App\Http\Controllers\TimeManagement\MedicalCertificateReviewController;
use App\Http\Controllers\TimeManagement\TimeApprovalController;
use App\Http\Controllers\TimeManagement\TimeEntryReviewController;
use App\Http\Controllers\TimeManagement\WorkScheduleExceptionController;
use App\Http\Controllers\VirtualOffice\DashboardController as VirtualOfficeDashboardController;
use App\Http\Controllers\VirtualOffice\TimeAdjustmentRequestController;
use App\Http\Controllers\VirtualOffice\TimeCardController;
use App\Http\Controllers\VirtualOffice\TimePunchController;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::get('/', HomeController::class)->name('home');
Route::get('trabalhe-conosco', [CareerController::class, 'index'])->name('careers.index');
Route::inertia('privacidade-e-lgpd', 'careers/privacy')->name('privacy');
Route::get('trabalhe-conosco/{slug}', [CareerController::class, 'show'])->name('careers.show');
Route::post('trabalhe-conosco/{slug}/candidatar', CareerApplicationController::class)->middleware('throttle:10,1')->name('careers.apply');

Route::middleware('guest')->group(function () {
    Route::get('candidato/entrar', [CandidateAuthController::class, 'create'])->name('candidate.login');
    Route::post('candidato/entrar', [CandidateAuthController::class, 'store'])->middleware('throttle:5,1')->name('candidate.login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('candidato', [CandidatePortalController::class, 'index'])->name('candidate.index');
    Route::get('candidato/candidaturas/{application}', [CandidateApplicationController::class, 'show'])->name('candidate.applications.show');
    Route::post('candidato/sair', [CandidateAuthController::class, 'destroy'])->name('candidate.logout');
    Route::get('candidato/candidaturas/{application}/disc', [DiscAssessmentController::class, 'show'])->name('candidate.disc.show');
    Route::post('candidato/candidaturas/{application}/disc/iniciar', [DiscAssessmentController::class, 'start'])->name('candidate.disc.start');
    Route::put('candidato/candidaturas/{application}/disc/respostas/{question}', [DiscAssessmentController::class, 'answer'])->name('candidate.disc.answer');
    Route::post('candidato/candidaturas/{application}/disc/concluir', [DiscAssessmentController::class, 'complete'])->name('candidate.disc.complete');
    Route::post('candidato/entrevistas/{interview}/responder', [CandidateInterviewScheduleController::class, 'respond'])->name('candidate.interviews.respond');
});

$authenticatedMiddleware = ['auth'];
$workosConfigured = app()->environment('production')
    && filled(config('services.workos.client_id'))
    && filled(config('services.workos.secret'));

if ($workosConfigured) {
    $authenticatedMiddleware[] = ValidateSessionWithWorkOS::class;
}

Route::middleware($authenticatedMiddleware)->group(function () {
    Route::get('dashboard', fn () => auth()->user()->hasRole('candidato')
        ? redirect()->route('candidate.index')
        : inertia('dashboard'))->name('dashboard');
    Route::get('hr', HrDashboardController::class)->name('hr.dashboard');
    Route::resource('hr/jobs', JobController::class)->only(['index', 'store', 'update', 'destroy'])->names('hr.jobs');
    Route::resource('hr/applications', ApplicationController::class)->only(['index', 'update', 'destroy'])->names('hr.applications');
    Route::get('hr/applications/{application}/resume', ApplicationResumeController::class)->name('hr.applications.resume');
    Route::post('hr/applications/{application}/screen', [ApplicationController::class, 'screen'])->name('hr.applications.screen');
    Route::post('hr/applications/{application}/extract', [ApplicationController::class, 'extract'])->name('hr.applications.extract');
    Route::post('hr/jobs/{job}/image', JobImageController::class)->name('hr.jobs.image.store');
    Route::resource('hr/departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy'])->names('hr.departments');
    Route::resource('hr/positions', PositionController::class)->only(['index', 'store', 'update', 'destroy'])->names('hr.positions');
    Route::get('hr/evaluations', [InterviewScheduleController::class, 'index'])->name('hr.evaluations.index');
    Route::post('hr/evaluations', [InterviewScheduleController::class, 'store'])->name('hr.evaluations.store');

    Route::prefix('virtual-office')
        ->name('virtual-office.')
        ->middleware('permission:intranet.access')
        ->group(function () {
            Route::get('/', VirtualOfficeDashboardController::class)->name('dashboard');
            Route::get('time-card', [TimeCardController::class, 'index'])
                ->middleware('permission:time-records.view-own')
                ->name('time-card.index');
            Route::get('time-punch', [TimePunchController::class, 'show'])
                ->middleware('permission:time-records.view-own')
                ->name('time-punch.show');
            Route::post('time-punch', [TimePunchController::class, 'store'])
                ->middleware(['permission:time-records.view-own', 'throttle:10,1'])
                ->name('time-punch.store');
            Route::post('time-adjustments', [TimeAdjustmentRequestController::class, 'store'])
                ->middleware('permission:time-records.request-adjustment')
                ->name('time-adjustments.store');
            Route::post('medical-certificates', [MedicalCertificateController::class, 'store'])
                ->middleware('permission:medical-certificates.submit')
                ->name('medical-certificates.store');
        });

    Route::get('medical-certificates/{justification}/document', [MedicalCertificateController::class, 'download'])
        ->name('medical-certificates.download');

    Route::prefix('personnel')->name('personnel.')->middleware('permission:time-records.manage')->group(function () {
        Route::get('time-card-settings', [TimeCardSettingsController::class, 'index'])->name('time-card-settings.index');
        Route::post('time-card-settings/groups', [TimeCardSettingsController::class, 'store'])->name('time-card-settings.groups.store');
        Route::put('time-card-settings/groups/{group}', [TimeCardSettingsController::class, 'update'])->name('time-card-settings.groups.update');
        Route::delete('time-card-settings/groups/{group}', [TimeCardSettingsController::class, 'destroy'])->name('time-card-settings.groups.destroy');
        Route::post('time-card-settings/assignments', [TimeCardSettingsController::class, 'assign'])->name('time-card-settings.assignments.store');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    });

    Route::prefix('personnel')->name('personnel.')->middleware('permission:time-records.approve|medical-certificates.review')->group(function () {
        Route::get('time-approvals', [TimeApprovalController::class, 'index'])->name('time-approvals.index');
        Route::get('time-approvals/employees/{employee}/time-card', [TimeApprovalController::class, 'timeCard'])->name('time-approvals.time-card');
        Route::patch('time-approvals/{adjustment}', [TimeApprovalController::class, 'update'])->name('time-approvals.update');
        Route::patch('time-entries/{entry}', [TimeEntryReviewController::class, 'update'])->name('time-entries.update');
        Route::post('work-schedule-exceptions', [WorkScheduleExceptionController::class, 'store'])->name('work-schedule-exceptions.store');
        Route::delete('work-schedule-exceptions/{exception}', [WorkScheduleExceptionController::class, 'destroy'])->name('work-schedule-exceptions.destroy');
        Route::patch('medical-certificates/{justification}', [MedicalCertificateReviewController::class, 'update'])->name('medical-certificates.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
