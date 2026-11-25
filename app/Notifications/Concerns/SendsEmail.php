<?php

namespace App\Notifications\Concerns;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\Channels\SafeMailChannel;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Adds email to a notification's channels (when allowed in Settings and the user has an email),
 * and builds a simple branded email from the notification's title / body / url.
 */
trait SendsEmail
{
    /** @param  array<int, string>  $channels */
    protected function withEmail(object $notifiable, array $channels): array
    {
        if (static::shouldEmail($notifiable)) {
            $channels[] = SafeMailChannel::class;
        }

        return $channels;
    }

    public static function shouldEmail(object $notifiable): bool
    {
        if (! Setting::get('email_enabled') || blank($notifiable->email ?? null)) {
            return false;
        }

        $isStaff = $notifiable instanceof User && $notifiable->hasAnyRole([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_MENTOR]);

        return (bool) Setting::get($isStaff ? 'email_staff' : 'email_students');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);
        $url = ! empty($data['url']) ? url($data['url']) : url('/');

        $mail = (new MailMessage)
            ->subject(($data['title'] ?? 'Update').' — '.config('app.name'))
            ->greeting('Assalam o Alaikum '.($notifiable->name ?? '').',')
            ->line($data['body'] ?? '');

        foreach ($this->mailExtraLines($notifiable) as $line) {
            $mail->line($line);
        }

        return $mail
            ->action($data['action'] ?? 'Open the portal', $url)
            ->salutation("Regards,\nLearning with Earning");
    }

    /** Override to add more lines to the email. */
    protected function mailExtraLines(object $notifiable): array
    {
        return [];
    }
}
