<?php
namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Immediate in-app/database notification.
 *
 * The $mail flag is kept for backward compatibility with older direct calls;
 * normal application flows should use NotificationService so email delivery is
 * queued separately without delaying the web request.
 */
class SirkelNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public bool $mail = false
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->mail ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url];
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
