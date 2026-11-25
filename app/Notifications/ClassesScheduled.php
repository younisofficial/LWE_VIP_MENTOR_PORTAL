<?php

namespace App\Notifications;

use App\Models\ClassSession;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** New live classes were scheduled (one message for the whole series). Sent to students and the mentor. */
class ClassesScheduled extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    /** @param  array<int, string>  $dates  local date/time labels of every class */
    public function __construct(public ClassSession $first, public array $dates) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database']);
    }

    private function isHost(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->id === $this->first->mentor_id;
    }

    public function toArray(object $notifiable): array
    {
        $count = count($this->dates);
        $course = $this->first->course?->title ?? 'your course';

        return [
            'title' => $this->isHost($notifiable) ? "You are teaching {$count} new live class".($count > 1 ? 'es' : '') : "{$count} new live class".($count > 1 ? 'es' : '').' scheduled',
            'body' => "{$course} · {$this->first->batch?->name}. First class: {$this->dates[0]}.",
            'url' => $this->isHost($notifiable) ? route('mentor.dashboard', [], false) : route('student.classes.index', [], false),
            'icon' => 'video',
            'action' => $this->isHost($notifiable) ? 'Open my classes' : 'See my classes',
        ];
    }

    protected function mailExtraLines(object $notifiable): array
    {
        $lines = ['Class times (Pakistan time):'];
        foreach (array_slice($this->dates, 0, 20) as $i => $d) {
            $lines[] = ($i + 1).'. '.$d;
        }
        if (count($this->dates) > 20) {
            $lines[] = '…and '.(count($this->dates) - 20).' more on the portal.';
        }
        $lines[] = $this->isHost($notifiable)
            ? 'Press "Start" on your dashboard up to 30 minutes before each class.'
            : 'The Join button opens a few minutes before each class. Join from inside the portal.';

        return $lines;
    }
}
