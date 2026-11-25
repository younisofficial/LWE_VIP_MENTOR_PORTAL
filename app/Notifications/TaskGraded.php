<?php

namespace App\Notifications;

use App\Models\TaskSubmission;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskGraded extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public TaskSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database']);
    }

    public function toArray(object $notifiable): array
    {
        $s = $this->submission;
        $redo = $s->status === 'resubmit';

        return [
            'title' => $redo ? 'Please improve your task' : 'Your task is checked',
            'body' => $s->task->title.($redo ? ' — your mentor asked you to submit again.' : " — {$this->formatMarks($s->marks)}/{$s->task->max_marks} marks."),
            'url' => route('student.tasks.show', $s->task_id, false),
            'icon' => 'tasks',
            'action' => 'See feedback',
        ];
    }

    private function formatMarks(?float $m): string
    {
        return $m === null ? '—' : rtrim(rtrim(number_format($m, 1), '0'), '.');
    }

    protected function mailExtraLines(object $notifiable): array
    {
        return $this->submission->feedback ? ['Feedback: '.$this->submission->feedback] : [];
    }
}
