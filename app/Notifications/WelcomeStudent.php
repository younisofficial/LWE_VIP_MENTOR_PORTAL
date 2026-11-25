<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Welcome email for a new student. $setPassword = account made by admin/CSV, so they still need a password. */
class WelcomeStudent extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public bool $setPassword = false) {}

    public function via(object $notifiable): array
    {
        // Email only: the in-app bell is not useful before the first login.
        return static::shouldEmail($notifiable) ? [Channels\SafeMailChannel::class] : [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to Learning with Earning',
            'body' => 'Your student account is ready. Your student ID is '.($notifiable->student_code ?? '—').'.',
            'url' => $this->setPassword ? route('password.request', [], false) : route('dashboard', [], false),
            'action' => $this->setPassword ? 'Set my password' : 'Open the portal',
        ];
    }

    protected function mailExtraLines(object $notifiable): array
    {
        return $this->setPassword
            ? ['Log in with your phone number. First press "Set my password" and enter your phone number — you will get a code to set your own password.']
            : ['Log in with your phone number or email and your password.'];
    }
}
