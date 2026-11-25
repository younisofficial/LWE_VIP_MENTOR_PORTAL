<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Notifications\Notification;

class EnrollmentActivated extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public Enrollment $enrollment) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => 'You are enrolled',
            'body' => "Your access to {$course->title} is now active.",
            'url' => route('student.my-courses.show', $course->slug, false),
            'icon' => 'check',
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $course = $this->enrollment->course;
        $batch = $this->enrollment->batch;
        $batchText = $batch ? " ({$batch->name})" : '';

        return "Assalam o Alaikum {$notifiable->name}! Aap ka admission {$course->title}{$batchText} mein confirm ho gaya hai. "
            .'Portal par login karein: '.route('student.today');
    }
}
