<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** A bell-only notice (no email): e.g. "task submitted" for mentors. */
class SimpleAlert extends Notification
{
    use Queueable;

    public function __construct(public string $title, public string $body, public string $url, public string $icon = 'bell') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'icon' => $this->icon];
    }
}
