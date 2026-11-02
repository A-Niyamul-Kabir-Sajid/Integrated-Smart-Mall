<?php

namespace App\Modules\SharedPlatform\Adapters;

use App\Modules\SharedPlatform\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Development gateway: writes messages to the application log instead of
 * sending them. The phone number is masked so logs carry no full numbers.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $phoneNumber, string $message): void
    {
        Log::info('SMS not sent (log gateway).', [
            'to' => $this->mask($phoneNumber),
            'message' => $message,
        ]);
    }

    private function mask(string $phoneNumber): string
    {
        $visible = 3;

        if (strlen($phoneNumber) <= $visible * 2) {
            return str_repeat('*', strlen($phoneNumber));
        }

        return substr($phoneNumber, 0, $visible + 1)
            .str_repeat('*', strlen($phoneNumber) - ($visible * 2) - 1)
            .substr($phoneNumber, -$visible);
    }
}
