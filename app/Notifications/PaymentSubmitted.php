<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Notifications\Notification;

/** In-app alert for admins: a student uploaded a payment. */
class PaymentSubmitted extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database']);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New payment to review',
            'body' => $this->payment->user->name.' · PKR '.number_format((float) $this->payment->amount).' · '.$this->payment->enrollment->course->title,
            'url' => route('admin.payments.index', ['status' => 'pending'], false),
            'icon' => 'money',
        ];
    }
}
