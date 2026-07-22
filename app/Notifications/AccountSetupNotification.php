<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly bool $initialSetup,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject($this->initialSetup ? 'Set up your ECRATS account' : 'Reset your ECRATS password')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line($this->initialSetup
                ? 'An ECRATS account has been created for you by an authorized project user.'
                : 'An authorized project user requested a new password link for your ECRATS account.')
            ->line('Your username is: '.$notifiable->username)
            ->action($this->initialSetup ? 'Set Up Password' : 'Reset Password', $url)
            ->line('This one-time link expires in seven days and becomes invalid after successful use.')
            ->line('ECRATS will never send or ask an account creator to choose your password.')
            ->salutation('KLD Research Ethics Section');
    }
}
