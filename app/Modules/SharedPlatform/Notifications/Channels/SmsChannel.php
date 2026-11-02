<?php

namespace App\Modules\SharedPlatform\Notifications\Channels;

use App\Modules\SharedPlatform\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * The "sms" notification channel. A notification using it implements
 * toSms($notifiable): string; the notifiable provides its number through
 * routeNotificationForSms(). Notifiables without a number are skipped.
 */
class SmsChannel
{
    public function __construct(private SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phoneNumber = $notifiable->routeNotificationFor('sms', $notification);

        if (! is_string($phoneNumber) || $phoneNumber === '') {
            return;
        }

        if (! method_exists($notification, 'toSms')) {
            throw new LogicException($notification::class.' must implement toSms() to use the sms channel.');
        }

        $this->gateway->send($phoneNumber, $notification->toSms($notifiable));
    }
}
