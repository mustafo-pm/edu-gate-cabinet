<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A simple in-app notification stored in the `notifications` table and shown
 * in the cabinet bell. `level` drives the accent colour (info|success|warning|danger).
 */
class CabinetNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message = '',
        public string $level = 'info',
        public ?string $url = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'level' => $this->level,
            'url' => $this->url,
        ];
    }
}
