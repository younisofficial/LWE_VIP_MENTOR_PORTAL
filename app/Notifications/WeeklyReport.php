<?php

namespace App\Notifications;

use App\Notifications\Channels\SafeMailChannel;
use App\Notifications\Concerns\SendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Monday morning summary for admins: last week's numbers + AI notes. */
class WeeklyReport extends Notification
{
    use Queueable, SendsEmail;

    public function __construct(public array $kpis, public ?array $ai, public string $period) {}

    public function via(object $notifiable): array
    {
        return $this->withEmail($notifiable, ['database']);
    }

    private function pkr(float $n): string
    {
        return 'PKR '.number_format($n);
    }

    private function change(?float $c): string
    {
        return $c === null ? '' : ' ('.($c >= 0 ? '+' : '').$c.'%)';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Weekly report — '.$this->period,
            'body' => $this->ai['headline'] ?? ($this->pkr($this->kpis['revenue']).' revenue · '.$this->kpis['enrollments'].' new enrollments · '.($this->kpis['attendance'] ?? '—').'% attendance'),
            'url' => route('admin.reports.index', ['range' => '7d'], false),
            'icon' => 'log',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $k = $this->kpis;
        $mail = (new MailMessage)
            ->subject('LWE weekly report — '.$this->period)
            ->greeting('Assalam o Alaikum '.$notifiable->name.',')
            ->line('**Revenue:** '.$this->pkr($k['revenue']).$this->change($k['revenue_change']))
            ->line('**New enrollments:** '.$k['enrollments'].$this->change($k['enrollments_change']).' · **Active students:** '.$k['active_students'])
            ->line('**Class attendance:** '.($k['attendance'] ?? '—').'% · **Classes held:** '.$k['classes_held'])
            ->line('**Tasks submitted:** '.$k['tasks_submitted'].' · **Quizzes taken:** '.$k['quizzes_taken'].' · **Certificates:** '.$k['certificates'])
            ->line('**At risk:** '.$k['at_risk'].' students · **Payments waiting:** '.$k['pending_payments'].' ('.$this->pkr($k['pending_amount']).')');

        if ($this->ai) {
            $mail->line('---')->line('**AI notes:** '.$this->ai['headline']);
            foreach ($this->ai['insights'] as $i) {
                $mail->line('• '.$i);
            }
            $mail->line('**Do this week:**');
            foreach ($this->ai['actions'] as $a) {
                $mail->line('→ '.$a);
            }
        }

        return $mail->action('Open full reports', url(route('admin.reports.index', ['range' => '7d'], false)))->salutation("Regards,\nLWE Learning Portal");
    }
}
