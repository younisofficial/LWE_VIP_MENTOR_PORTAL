<?php

namespace App\Notifications;

use App\Models\Task;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskPublished extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    public function toArray(object $notifiable): array
    {
        $due = $this->task->due_at?->setTimezone(config('lwe.timezone'))->format('D d M, g:i A');

        return [
            'title' => 'New task: '.$this->task->title,
            'body' => $this->task->course->title.($due ? " · Submit by {$due}" : '').' · '.$this->task->max_marks.' marks',
            'url' => route('student.tasks.show', $this->task, false),
            'icon' => 'tasks',
            'action' => 'Open the task',
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $d = $this->toArray($notifiable);

        return "📝 {$d['title']}\n{$d['body']}\n".url($d['url']);
    }
}
