<?php

namespace App\Modules\SharedPlatform\Contracts;

/**
 * Boundary to an external SMS provider. Swap the binding in
 * SharedPlatformServiceProvider to integrate a real provider.
 */
interface SmsGateway
{
    /**
     * Send a text message to a phone number in E.164 format (+8801712345678).
     */
    public function send(string $phoneNumber, string $message): void;
}
