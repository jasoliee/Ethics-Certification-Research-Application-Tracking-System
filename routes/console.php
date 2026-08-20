<?php

use App\Models\DatabaseNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => DatabaseNotification::onlyTrashed()
    ->where('deleted_at', '<=', now()->subDays(7))
    ->forceDelete())
    ->dailyAt('02:15')
    ->name('purge-expired-notification-bin')
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ecrats:mail-test {email : Recipient email address}', function (string $email): int {
    $this->info('Sending ECRATS test email...');
    $this->line('Mailer: '.config('mail.default'));
    $this->line('From: '.config('mail.from.address'));

    Mail::raw(
        "This is a test email from your local ECRATS setup.\n\nIf you received this, Laravel mail delivery is working.",
        function ($message) use ($email): void {
            $message
                ->to($email)
                ->subject('ECRATS Mail Test');
        },
    );

    $this->info('Test email sent. Check the recipient inbox or spam folder.');

    return self::SUCCESS;
})->purpose('Send a safe test email using the configured Laravel mailer');
