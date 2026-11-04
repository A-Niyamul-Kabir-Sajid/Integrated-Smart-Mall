<?php

namespace Tests\Feature\Modules\SharedPlatform;

use App\Modules\SharedPlatform\Adapters\LogSmsGateway;
use App\Modules\SharedPlatform\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\Notifications\ExampleTextNotification;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsGateway::class, new class($this->sent) implements SmsGateway
        {
            public function __construct(private array &$sent) {}

            public function send(string $phoneNumber, string $message): void
            {
                $this->sent[] = [$phoneNumber, $message];
            }
        });
    }

    public function test_sms_notifications_are_sent_through_the_bound_gateway(): void
    {
        Notification::route('sms', '+8801712345678')->notify(new ExampleTextNotification);

        $this->assertSame([['+8801712345678', 'Your order is ready for collection.']], $this->sent);
    }

    public function test_notifiables_without_a_phone_number_are_skipped(): void
    {
        Notification::route('mail', 'customer@example.com')->notify(new ExampleTextNotification);

        $this->assertSame([], $this->sent);
    }

    public function test_log_gateway_masks_the_phone_number(): void
    {
        Log::spy();

        (new LogSmsGateway)->send('+8801712345678', 'Hello');

        Log::shouldHaveReceived('info')->once()->with('SMS not sent (log gateway).', [
            'to' => '+880*******678',
            'message' => 'Hello',
        ]);
    }

    public function test_application_binds_the_log_gateway_by_default(): void
    {
        $this->refreshApplication();

        $this->assertInstanceOf(LogSmsGateway::class, $this->app->make(SmsGateway::class));
    }
}
