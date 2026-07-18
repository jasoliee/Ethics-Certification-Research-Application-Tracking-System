<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DashboardUpdateNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }
}
