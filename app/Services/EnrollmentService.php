<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\EnrollmentReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function submit(User $user, Training $training, int $deliveryModeId, ?int $sessionId): Enrollment
    {
        if (! $training->is_published) {
            abort(404);
        }

        $enrollment = DB::transaction(function () use ($user, $training, $deliveryModeId, $sessionId): Enrollment {
            $modeIsAvailable = $training->deliveryModes()
                ->whereKey($deliveryModeId)
                ->where('delivery_modes.is_active', true)
                ->exists();

            if (! $modeIsAvailable) {
                throw ValidationException::withMessages([
                    'delivery_mode_id' => 'Cette modalité n’est pas proposée pour cette formation.',
                ]);
            }

            $registrationKey = hash('sha256', implode('|', [
                $user->getKey(),
                $training->getKey(),
                $sessionId ?? 'no-session',
            ]));

            $session = null;

            if ($sessionId !== null) {
                $session = TrainingSession::query()
                    ->where('training_id', $training->id)
                    ->where('status', 'scheduled')
                    ->whereKey($sessionId)
                    ->lockForUpdate()
                    ->first();

                if ($session === null || ($session->starts_at !== null && $session->starts_at->isPast())) {
                    throw ValidationException::withMessages([
                        'training_session_id' => 'Cette session n’est plus disponible.',
                    ]);
                }

                if ($session->delivery_mode_id !== null && (int) $session->delivery_mode_id !== $deliveryModeId) {
                    throw ValidationException::withMessages([
                        'delivery_mode_id' => 'La modalité choisie ne correspond pas à cette session.',
                    ]);
                }

                $existingEnrollment = Enrollment::query()->where('registration_key', $registrationKey)->first();

                if ($existingEnrollment !== null) {
                    return $existingEnrollment;
                }

                if ($session->capacity !== null) {
                    $activeEnrollmentCount = Enrollment::query()
                        ->where('training_session_id', $session->id)
                        ->whereNotIn('status', ['cancelled'])
                        ->count();

                    if ($activeEnrollmentCount >= $session->capacity) {
                        throw ValidationException::withMessages([
                            'training_session_id' => 'Cette session a atteint sa capacité maximale.',
                        ]);
                    }
                }
            }

            if ($sessionId === null) {
                $existingEnrollment = Enrollment::query()->where('registration_key', $registrationKey)->first();

                if ($existingEnrollment !== null) {
                    return $existingEnrollment;
                }
            }

            return Enrollment::firstOrCreate(
                ['registration_key' => $registrationKey],
                [
                    'user_id' => $user->getKey(),
                    'training_id' => $training->getKey(),
                    'training_session_id' => $session?->getKey(),
                    'delivery_mode_id' => $deliveryModeId,
                    'status' => 'pending',
                    'agreed_amount' => $training->price,
                    'currency' => $training->currency,
                    'registered_at' => now(),
                ],
            );
        });

        if ($enrollment->wasRecentlyCreated) {
            $enrollment->load('training:id,title');

            $administrators = User::query()
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('slug', 'administrateur'))
                ->get();

            Notification::send($administrators, new EnrollmentReceived($enrollment));
        }

        return $enrollment;
    }
}
