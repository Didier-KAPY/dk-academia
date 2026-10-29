<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Notifications\Notification;

class EnrollmentReceived extends Notification
{
    public function __construct(public Enrollment $enrollment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'enrollment_received',
            'enrollment_id' => $this->enrollment->getKey(),
            'training_title' => $this->enrollment->training->title,
            'message' => 'Nouvelle demande d’inscription à traiter.',
        ];
    }
}
