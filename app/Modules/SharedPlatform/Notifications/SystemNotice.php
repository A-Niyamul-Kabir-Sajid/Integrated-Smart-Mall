<?php

namespace App\Modules\SharedPlatform\Notifications;

use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A platform message shown in the user's notification list, e.g. changed mall
 * opening hours. Delivery channels follow the user's notification preferences.
 */
class SystemNotice extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $actionUrl = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationPreferences::class)->channelsFor($notifiable, NotificationType::SystemNotice);
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationType::SystemNotice->value;
    }

    /**
     * @return array{title: string, body: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->title)->line($this->body);

        return $this->actionUrl === null ? $message : $message->action(__('Open'), $this->actionUrl);
    }
}
