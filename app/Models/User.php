<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $workos_id
 * @property string|null $remember_token
 * @property string $avatar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'email', 'password', 'tracks_time', 'workos_id', 'avatar', 'cpf', 'birth_date',
    'phone', 'gender', 'postal_code', 'address', 'address_number',
    'address_complement', 'district', 'city', 'state',
])]
#[Hidden(['workos_id', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = [
        'tracks_time' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
            'password' => 'hashed',
            'tracks_time' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function timeAdjustmentRequests(): HasMany
    {
        return $this->hasMany(TimeAdjustmentRequest::class);
    }

    public function hourBankTransactions(): HasMany
    {
        return $this->hasMany(HourBankTransaction::class);
    }

    public function vacationPeriods(): HasMany
    {
        return $this->hasMany(VacationPeriod::class);
    }

    public function workScheduleAssignments(): HasMany
    {
        return $this->hasMany(WorkScheduleAssignment::class);
    }

    public function workScheduleExceptions(): HasMany
    {
        return $this->hasMany(WorkScheduleException::class);
    }

    public function absenceJustifications(): HasMany
    {
        return $this->hasMany(AbsenceJustification::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'candidate_id');
    }

    public function organizedInterviews(): HasMany
    {
        return $this->hasMany(InterviewSchedule::class, 'organizer_id');
    }

    public function calendarConnection(): HasOne
    {
        return $this->hasOne(CalendarConnection::class);
    }
}
