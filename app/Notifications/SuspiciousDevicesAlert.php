<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Notifications\Notification;

class SuspiciousDevicesAlert extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public User $student, public int $devices) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database']);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Many devices on one account',
            'body' => "{$this->student->name} ({$this->student->student_code}) logged in from {$this->devices} devices in 24 hours.",
            'url' => route('admin.students.show', $this->student->id, false),
            'icon' => 'alert',
        ];
    }
}
