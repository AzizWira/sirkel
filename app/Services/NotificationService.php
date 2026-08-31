<?php
namespace App\Services;

use App\Models\User;
use App\Notifications\{SirkelMailNotification, SirkelNotification};

class NotificationService
{
    public function send(User $user, string $title, string $message, ?string $url = null, bool $mail = true): void
    {
        // In-app links are stored as same-origin paths. This prevents a localhost
        // vs 127.0.0.1 (or HTTP vs HTTPS behind a proxy) mismatch from dropping
        // the authenticated session when a user opens a notification.
        $user->notify(new SirkelNotification(
            $title,
            $message,
            $this->inAppPath($url),
            false
        ));

        // Email still needs an absolute URL because it is opened outside SIRKEL.
        if ($mail) {
            $user->notify(new SirkelMailNotification(
                $title,
                $message,
                $this->mailUrl($url)
            ));
        }
    }

    public function inAppPath(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $path .= '#' . $parts['fragment'];
        }

        return $path;
    }

    private function mailUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
