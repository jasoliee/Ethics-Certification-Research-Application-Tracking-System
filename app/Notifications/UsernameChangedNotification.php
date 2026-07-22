<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UsernameChangedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $username) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your ECRATS username was updated')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line('An authorized correction changed your ECRATS username.')
            ->line('Your new username is: '.$this->username)
            ->line('Your password and account permissions were not changed.')
            ->salutation('KLD Research Ethics Section');
    }
}
