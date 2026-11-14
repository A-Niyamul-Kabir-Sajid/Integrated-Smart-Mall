<?php

namespace Tests\Feature\Modules\MallOwnerAdmin;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Services\ComplaintService;
use App\Modules\MallOwnerAdmin\Services\WorkOrderService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Services\MaintenanceRequestService;
use App\Modules\ShopOwnerOperations\Services\ReceiptService;
use Database\Factories\PurchaseFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A reference number that happens to clash is retried with a new one. The retry must also work inside
 * an open database transaction (every request that wraps its work in one): on PostgreSQL a failed
 * insert aborts the whole transaction unless it ran in a savepoint. The test runs inside the
 * RefreshDatabase transaction, so it fails on PostgreSQL if a retry loop lacks its savepoint.
 */
class NumberCollisionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    /**
     * Makes the next random strings of one length come out as AAAA…, AAAA…, BBBB…, so the second number
     * generated clashes with the first. Other lengths (session ids and the like) stay random, so
     * nothing else uses up the planned values.
     */
    private function colliding(int $length): void
    {
        $queue = [str_repeat('A', $length), str_repeat('A', $length), str_repeat('B', $length)];

        Str::createRandomStringsUsing(function (int $requested) use (&$queue, $length): string {
            if ($requested === $length && $queue !== []) {
                return array_shift($queue);
            }

            return substr(bin2hex(random_bytes(intdiv($requested, 2) + 1)), 0, $requested);
        });
    }

    public function test_a_complaint_number_clash_is_retried_without_breaking_the_transaction(): void
    {
        $mall = Mall::factory()->create();
        $customer = User::factory()->create();
        $data = ['category' => 'noise', 'subject' => 'Loud music', 'description' => 'The music near the atrium is far too loud.'];
        $this->colliding(8);

        $first = app(ComplaintService::class)->file($customer, $mall, null, $data);
        $second = app(ComplaintService::class)->file($customer, $mall, null, $data);

        $this->assertSame('CMP-AAAAAAAA', $first->complaint_number);
        $this->assertSame('CMP-BBBBBBBB', $second->complaint_number);
    }

    public function test_a_work_order_number_clash_is_retried_without_breaking_the_transaction(): void
    {
        $mall = Mall::factory()->create();
        $admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($admin, $mall, RoleCode::MallAdmin);
        $data = ['title' => 'Replace corridor light', 'description' => 'The light outside the lift flickers.', 'priority' => 'normal'];
        $this->colliding(8);

        $first = app(WorkOrderService::class)->create($admin, $mall, $data);
        $second = app(WorkOrderService::class)->create($admin, $mall, $data);

        $this->assertSame('WO-AAAAAAAA', $first->work_order_number);
        $this->assertSame('WO-BBBBBBBB', $second->work_order_number);
    }

    public function test_a_maintenance_request_number_clash_is_retried_without_breaking_the_transaction(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($owner, $shop, RoleCode::ShopOwner);
        $data = ['title' => 'Leaking tap', 'description' => 'The tap in the back room leaks.', 'category' => 'plumbing', 'priority' => 'urgent'];
        $this->colliding(8);

        $first = app(MaintenanceRequestService::class)->create($owner, $shop, $data);
        $second = app(MaintenanceRequestService::class)->create($owner, $shop, $data);

        $this->assertSame('MNT-AAAAAAAA', $first->request_number);
        $this->assertSame('MNT-BBBBBBBB', $second->request_number);
    }

    public function test_a_receipt_number_clash_is_retried_without_breaking_the_transaction(): void
    {
        $shop = Shop::factory()->create();
        $first = PurchaseFactory::new()->state(['shop_id' => $shop->getKey()])->withLine()->create();
        $second = PurchaseFactory::new()->state(['shop_id' => $shop->getKey()])->withLine()->create();
        $this->colliding(6);

        $one = app(ReceiptService::class)->issue($first);
        $two = app(ReceiptService::class)->issue($second);

        $this->assertStringEndsWith('-AAAAAA', $one->receipt_number);
        $this->assertStringEndsWith('-BBBBBB', $two->receipt_number);
    }
}
