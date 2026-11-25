<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Notifications\Notification;

class PaymentRejected extends Notification implements ShouldQueue
{
    use Queueable, SendsEmail;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database', WhatsAppChannel::class]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment not approved',
            'body' => $this->payment->review_note ?: 'Please check your payment details and upload again.',
            'url' => route('student.payments.create', $this->payment->enrollment_id, false),
            'icon' => 'alert',
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $reason = $this->payment->review_note ? ' Wajah: '.$this->payment->review_note : '';

        return "{$notifiable->name}, aap ki payment approve nahi ho saki.{$reason} Dobara upload karein: "
            .route('student.payments.create', $this->payment->enrollment_id);
    }
}
