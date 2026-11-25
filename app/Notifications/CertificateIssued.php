<?php

namespace App\Notifications;

use App\Models\Certificate;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CertificateIssued extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => '🎓 Congratulations! Your certificate is ready',
            'body' => $this->certificate->course->title.' · '.$this->certificate->grade.($this->certificate->score !== null ? " · {$this->certificate->score}%" : '').' · '.$this->certificate->code,
            'url' => route('certificates.show', $this->certificate->code, false),
            'icon' => 'passport',
            'action' => 'Open my certificate',
        ];
    }

    protected function mailExtraLines(object $notifiable): array
    {
        return ['Your Skill Passport is updated with the verified skills. Share it with clients and employers: '.route('passport.show', $notifiable->passportCode())];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "🎓 Mubarak ho {$notifiable->name}! {$this->certificate->course->title} ka certificate tayyar hai ({$this->certificate->code}).\n".route('certificates.show', $this->certificate->code);
    }
}
