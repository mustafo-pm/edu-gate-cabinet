<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Component;

/**
 * In-app notification bell. Shared by the merchant and PSP cabinets — the
 * guard is passed at mount so each cabinet reads its own signed-in user's
 * database notifications.
 */
class NotificationBell extends Component
{
    public string $guard = 'merchant';

    public function mount(string $guard = 'merchant'): void
    {
        $this->guard = $guard;
    }

    private function user(): ?Authenticatable
    {
        return auth($this->guard)->user();
    }

    public function markAllRead(): void
    {
        $this->user()?->unreadNotifications->markAsRead();
    }

    public function markRead(string $id): void
    {
        $this->user()?->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function render()
    {
        $user = $this->user();

        return view('livewire.notification-bell', [
            'notifications' => $user ? $user->notifications()->latest()->limit(8)->get() : collect(),
            'unread' => $user ? $user->unreadNotifications()->count() : 0,
        ]);
    }
}
