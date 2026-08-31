<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Queued email companion for operational SIRKEL notifications. */
class SirkelMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line($this->message);

        if ($this->url) {
            $mail->action('Buka SIRKEL', $this->url);
        }

        return $mail->line('Notifikasi ini dikirim oleh SIRKEL.');
    }
}
