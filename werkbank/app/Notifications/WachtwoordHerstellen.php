<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Nederlandse mail met de link om een nieuw wachtwoord te kiezen. */
class WachtwoordHerstellen extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Nieuw wachtwoord voor '.config('app.name'))
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Je hebt gevraagd om een nieuw wachtwoord. Klik op de knop om er een te kiezen.')
            ->action('Nieuw wachtwoord kiezen', route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]))
            ->line("De link is {$minutes} minuten geldig.")
            ->line('Heb je dit niet zelf gevraagd? Dan hoef je niets te doen; je wachtwoord blijft ongewijzigd.')
            ->salutation('Groet, '.config('app.name'));
    }
}
