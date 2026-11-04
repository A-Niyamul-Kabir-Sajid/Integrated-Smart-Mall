<?php

namespace App\Modules\AccountsAccess\Notifications;

use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the account holder their password changed, so an unexpected change is noticed.
 */
class PasswordChanged extends Notification
{
    use Queueable;

    public function __construct(public readonly string $how) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationPreferences::class)->channelsFor($notifiable, NotificationType::AccountSecurity);
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationType::AccountSecurity->value;
    }

    /**
     * @return array{title: string, body: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your password was changed'),
            'body' => $this->how === 'reset'
                ? __('Your password was reset with an email link. All devices were signed out.')
                : __('Your password was changed from your account page. Other devices were signed out.'),
            'action_url' => null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your password was changed'))
            ->line($this->toArray($notifiable)['body'])
            ->line(__('If this was not you, reset your password now and contact the mall information desk.'));
    }
}
