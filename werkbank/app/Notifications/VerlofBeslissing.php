<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Mail aan de medewerker zodra over een verlofaanvraag is beslist. */
class VerlofBeslissing extends Notification
{
    public function __construct(public LeaveRequest $leave) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->leave->status === 'goedgekeurd';
        $mail = (new MailMessage)
            ->subject('Verlof '.($approved ? 'goedgekeurd' : 'afgewezen').': '.$this->leave->period())
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Je verlofaanvraag ('.$this->leave->typeLabel().', '.$this->leave->period().', '.number_format($this->leave->hours(), 1, ',', '.').' uur) is '.($approved ? 'goedgekeurd' : 'afgewezen').'.');
        if ($this->leave->decision_note) {
            $mail->line('Toelichting: '.$this->leave->decision_note);
        }

        return $mail->action('Bekijk je verlof', route('verlof.index'))->salutation('Groet, '.config('app.name'));
    }
}
