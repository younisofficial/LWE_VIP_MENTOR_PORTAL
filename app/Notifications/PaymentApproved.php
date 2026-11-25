<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Notifications\Notification;

class PaymentApproved extends Notification implements ShouldQueue
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
            'title' => 'Payment approved',
            'body' => 'PKR '.number_format((float) $this->payment->amount).' received for '.$this->payment->enrollment->course->title.'.',
            'url' => route('student.my-courses.show', $this->payment->enrollment->course->slug, false),
            'icon' => 'check',
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "{$notifiable->name}, aap ki payment PKR ".number_format((float) $this->payment->amount)
            .' approve ho gayi hai. '.$this->payment->enrollment->course->title.' ab aap ke portal par khul gaya hai.';
    }
}
