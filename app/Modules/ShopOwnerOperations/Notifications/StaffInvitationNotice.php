<?php

namespace App\Modules\ShopOwnerOperations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail sent to the invited address (which may not have an account yet) with the
 * link that accepts a staff invitation.
 */
class StaffInvitationNotice extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $shopName,
        public readonly string $acceptUrl,
        public readonly string $functionLabel,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You are invited to work at :shop', ['shop' => $this->shopName]))
            ->line(__('You were invited to join :shop as :function.', ['shop' => $this->shopName, 'function' => $this->functionLabel]))
            ->line(__('Sign in or register with this email address, then open the link. It expires in 7 days.'))
            ->action(__('Accept the invitation'), $this->acceptUrl);
    }
}
