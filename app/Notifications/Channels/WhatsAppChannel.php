<?php

namespace App\Notifications\Channels;

use App\Services\WaFlowClient;
use Illuminate\Notifications\Notification;

/**
 * Notification channel: add 'whatsapp' to via() and implement toWhatsApp($notifiable): string.
 */
class WhatsAppChannel
{
    public function __construct(private WaFlowClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! $phone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $this->client->send($phone, $notification->toWhatsApp($notifiable));
    }
}
