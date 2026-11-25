<?php

namespace App\Notifications;

use App\Models\QaQuestion;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** kind: asked (to mentors) · answered (to the student who asked) */
class QaActivity extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public QaQuestion $question, public string $kind, public string $byName = '') {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database']);
    }

    public function toArray(object $notifiable): array
    {
        $q = $this->question;

        return [
            'title' => $this->kind === 'asked' ? 'New question in '.($q->batch?->name ?? $q->course?->title ?? 'Q&A') : 'Your question has a new answer',
            'body' => $this->kind === 'asked' ? "{$this->byName}: {$q->title}" : "{$this->byName} answered: {$q->title}",
            'url' => route('qa.show', $q, false),
            'icon' => 'chat',
            'action' => $this->kind === 'asked' ? 'Answer it' : 'Read the answer',
        ];
    }
}
