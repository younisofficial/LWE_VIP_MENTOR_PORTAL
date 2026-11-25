<?php

namespace App\Notifications;

use App\Models\ClassReminder;
use App\Models\ClassSession;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Notifications\Notification;

/** Class cancelled or moved to a new time. */
class ClassChanged extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public ClassSession $session, public string $kind) {}

    public function via(object $notifiable): array
    {
        ClassReminder::updateOrCreate(
            ['class_session_id' => $this->session->id, 'user_id' => $notifiable->id, 'kind' => $this->kind],
            ['sent_at' => now()],
        );

        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    private function when(): string
    {
        return $this->session->localStart()->format('D d M, g:i A');
    }

    public function toArray(object $notifiable): array
    {
        $url = ($notifiable->id ?? null) === $this->session->mentor_id
            ? route('mentor.dashboard', [], false)
            : route('student.classes.index', [], false);

        return $this->kind === 'cancelled'
            ? ['title' => 'Class cancelled', 'body' => "{$this->session->title} ({$this->when()}) is cancelled.".($this->session->cancel_reason ? ' '.$this->session->cancel_reason : ''), 'url' => $url]
            : ['title' => 'Class time changed', 'body' => "{$this->session->title} is now on {$this->when()}.", 'url' => $url];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->kind === 'cancelled'
            ? "{$notifiable->name}, {$this->session->title} ki class ({$this->when()}) cancel ho gayi hai.".($this->session->cancel_reason ? " Wajah: {$this->session->cancel_reason}." : '').' Nayi date portal par dekhein.'
            : "{$notifiable->name}, {$this->session->title} ki class ka waqt badal gaya hai. Naya waqt: {$this->when()}.";
    }
}
