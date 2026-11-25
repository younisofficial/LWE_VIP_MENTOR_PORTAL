<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Friendly nudge to a student who is falling behind (sent at most once a week). */
class StudentAtRisk extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public Enrollment $enrollment) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    public function toArray(object $notifiable): array
    {
        $notes = collect($this->enrollment->progress_notes ?? [])->take(3)->implode(' · ');

        return [
            'title' => 'Aap ka course peeche reh raha hai — '.$this->enrollment->course->title,
            'body' => $notes ?: 'Thori si mehnat se aap certificate tak pohanch sakte hain.',
            'url' => route('student.passport.index', [], false),
            'icon' => 'alert',
            'action' => 'See what is left',
        ];
    }

    protected function mailExtraLines(object $notifiable): array
    {
        return ['Aaj sirf 20 minute dein: ek pending task jama karein ya class ki recording dekhein. Koi mushkil ho to Q&A mein poochein — mentor madad karenge.'];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $d = $this->toArray($notifiable);

        return "Assalam o Alaikum {$notifiable->name}! {$d['title']}\n{$d['body']}\nAaj 20 minute dein 💪 ".url($d['url']);
    }
}
