<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps()
            ->withPivot('is_primary')
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('name');
    }

    public function hasRole(string ...$roles): bool
    {
        return $roles !== []
            && $this->roles()->whereIn('slug', $roles)->exists();
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn (Builder $query): Builder => $query->where('slug', $permission))
            ->exists();
    }

    public function primaryRole(): ?Role
    {
        return $this->roles()->wherePivot('is_primary', true)->first()
            ?? $this->roles()->first();
    }

    public function createdTrainings(): HasMany
    {
        return $this->hasMany(Training::class, 'created_by');
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'instructor_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function progressions(): HasMany
    {
        return $this->hasMany(Progression::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function evaluationAttempts(): HasMany
    {
        return $this->hasMany(EvaluationAttempt::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function createdClients(): HasMany
    {
        return $this->hasMany(Client::class, 'created_by');
    }

    public function createdQuotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'created_by');
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    public function assignedProjectTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'assigned_to');
    }

    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'technician_id');
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'cashier_id');
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function validatedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'validated_by');
    }

    public function recordedCashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'recorded_by');
    }

    public function validatedCashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'validated_by');
    }

    public function trainerRemunerations(): HasMany
    {
        return $this->hasMany(TrainerRemuneration::class, 'trainer_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function trainerProfile(): HasOne
    {
        return $this->hasOne(TrainerProfile::class);
    }
}
