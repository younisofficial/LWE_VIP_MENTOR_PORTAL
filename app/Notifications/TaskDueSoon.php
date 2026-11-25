<?php

namespace App\Notifications;

use App\Models\Task;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskDueSoon extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => '⏰ Task due soon: '.$this->task->title,
            'body' => 'Submit by '.$this->task->due_at->setTimezone(config('lwe.timezone'))->format('D d M, g:i A').' · '.$this->task->course->title,
            'url' => route('student.tasks.show', $this->task, false),
            'icon' => 'tasks',
            'action' => 'Submit now',
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $d = $this->toArray($notifiable);

        return "{$d['title']}\n{$d['body']}\n".url($d['url']);
    }
}
