<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** For admins and the batch mentor: a student joined a course on their own. */
class StudentEnrolled extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public Enrollment $enrollment) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database']);
    }

    public function toArray(object $notifiable): array
    {
        $e = $this->enrollment;
        $status = $e->status === 'active' ? 'active' : 'waiting for payment';

        return [
            'title' => 'New enrollment',
            'body' => "{$e->user->name} ({$e->user->student_code}) joined {$e->course->title}".($e->batch ? " · {$e->batch->name}" : '')." — {$status}.",
            'url' => $notifiable->isStaff() ? route('admin.enrollments.index', ['search' => $e->user->student_code], false) : route('mentor.dashboard', [], false),
            'icon' => 'users',
        ];
    }
}
