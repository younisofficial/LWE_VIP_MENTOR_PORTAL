<?php

namespace App\Notifications;

use App\Models\Quiz;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class QuizPublished extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public Quiz $quiz) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    public function toArray(object $notifiable): array
    {
        $due = $this->quiz->due_at?->setTimezone(config('lwe.timezone'))->format('D d M, g:i A');

        return [
            'title' => 'New quiz: '.$this->quiz->title,
            'body' => $this->quiz->course->title.($this->quiz->time_limit_minutes ? " · {$this->quiz->time_limit_minutes} minutes" : '').($due ? " · Open until {$due}" : ''),
            'url' => route('student.quizzes.index', [], false),
            'icon' => 'check',
            'action' => 'Take the quiz',
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $d = $this->toArray($notifiable);

        return "✅ {$d['title']}\n{$d['body']}\n".url($d['url']);
    }
}
