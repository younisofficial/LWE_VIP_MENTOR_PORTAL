<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Normal Laravel mail channel, but a mail problem (wrong SMTP password, server down)
 * is only logged. Approving a payment or scheduling a class must never fail because of email.
 */
class SafeMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        try {
            return parent::send($notifiable, $notification);
        } catch (Throwable $e) {
            Log::warning('[mail] '.class_basename($notification).' to user '.($notifiable->id ?? '?').' failed: '.$e->getMessage());

            return null;
        }
    }
}
