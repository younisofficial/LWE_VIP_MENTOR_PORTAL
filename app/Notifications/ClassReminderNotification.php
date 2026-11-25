<?php

namespace App\Notifications;

use App\Models\ClassSession;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Notifications\Notification;

/** Reminder before class (day / hour / start) or after a missed class. */
class ClassReminderNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public ClassSession $session, public string $kind) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    private function when(): string
    {
        return $this->session->localStart()->format('g:i A');
    }

    public function toArray(object $notifiable): array
    {
        [$title, $body] = match ($this->kind) {
            'day' => ['Class tomorrow', "{$this->session->title} at {$this->when()}"],
            'hour' => ['Class in 1 hour', "{$this->session->title} at {$this->when()}"],
            'start' => ['Class is starting', 'Join now: '.$this->session->title],
            default => ['You missed a class', "{$this->session->title} — notes will be on the Live Classes page."],
        };

        if (($notifiable->id ?? null) === $this->session->mentor_id) {
            // The mentor gets "start the class" wording and their own dashboard link.
            $body = "{$this->session->title} at {$this->when()} — press Start on your dashboard (opens 30 minutes before).";

            return ['title' => $title.' (you are teaching)', 'body' => $body, 'url' => route('mentor.dashboard', [], false), 'action' => 'Open my dashboard'];
        }

        return ['title' => $title, 'body' => $body, 'url' => $this->kind === 'start'
            ? route('student.classes.join', $this->session, false)
            : route('student.classes.index', [], false)];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $link = route('student.classes.join', $this->session);

        return match ($this->kind) {
            'day' => "Assalam o Alaikum {$notifiable->name}! Kal {$this->when()} baje aap ki class hai: {$this->session->title}.",
            'hour' => "{$notifiable->name}, 1 ghante mein ({$this->when()}) aap ki class hai: {$this->session->title}. Tayyar rahein.",
            'start' => "{$notifiable->name}, class shuru ho rahi hai! Portal par login karke Join karein: {$link}",
            default => "{$notifiable->name}, aap aaj ki class ({$this->session->title}) miss kar gaye. Class ke notes portal ke Live Classes page par dekhein.",
        };
    }
}
