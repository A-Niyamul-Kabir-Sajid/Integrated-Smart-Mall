<?php

namespace Tests\Fixtures\Notifications;

use Illuminate\Notifications\Notification;

final class ExampleTextNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): string
    {
        return 'Your order is ready for collection.';
    }
}
