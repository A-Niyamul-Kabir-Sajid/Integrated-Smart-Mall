<?php

namespace Tests\Feature\Modules\MallOwnerAdmin;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Enums\ComplaintStatus;
use App\Modules\MallOwnerAdmin\Models\Complaint;
use App\Modules\MallOwnerAdmin\Models\ComplaintEvent;
use App\Modules\MallOwnerAdmin\Services\ComplaintService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Models\AuditLog;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    private User $colleague;

    private User $customer;

    private ComplaintService $complaints;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->admin = $this->administratorOf($this->mall);
        $this->colleague = $this->administratorOf($this->mall);
        $this->customer = User::factory()->create();
        $this->complaints = app(ComplaintService::class);
    }

    private function administratorOf(Mall $mall): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantMallRole($user, $mall, RoleCode::MallAdmin);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(array $overrides = []): array
    {
        return ['category' => 'cleanliness', 'subject' => 'Dirty washroom', 'description' => 'The washroom on the first floor has not been cleaned all day.', ...$overrides];
    }

    private function file(?Shop $shop = null, array $overrides = []): Complaint
    {
        return $this->complaints->file($this->customer, $this->mall, $shop, $this->data($overrides));
    }

    public function test_filing_records_the_complaint_its_first_history_entry_an_audit_record_and_tells_the_administrators(): void
    {
        Notification::fake();
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);

        $complaint = $this->file($shop);

        $this->assertMatchesRegularExpression('/^CMP-[A-Z0-9]{8}$/', $complaint->complaint_number);
        $this->assertSame(ComplaintStatus::Open, $complaint->status);
        $this->assertSame($shop->getKey(), $complaint->shop_id);
        $this->assertSame([null => 'open'], $complaint->events->mapWithKeys(fn ($event) => [$event->from_status => $event->to_status])->all());
        $this->assertTrue(AuditLog::query()->where('action', 'complaint.filed')->where('mall_id', $this->mall->getKey())->exists());
        Notification::assertSentTo([$this->admin, $this->colleague], SystemNotice::class);
        Notification::assertNotSentTo($this->customer, SystemNotice::class);
    }

    public function test_the_input_and_the_shop_are_validated_and_the_open_limit_applies(): void
    {
        $foreignShop = Shop::factory()->create();
        $draftShop = Shop::factory()->draft()->create(['mall_id' => $this->mall->getKey()]);

        foreach ([
            ['complaint.invalid_category', null, ['category' => 'nonsense']],
            ['complaint.invalid_subject', null, ['subject' => 'ab']],
            ['complaint.invalid_description', null, ['description' => 'too short']],
            ['complaint.invalid_shop', $foreignShop, []],
            ['complaint.invalid_shop', $draftShop, []],
        ] as [$rule, $shop, $overrides]) {
            try {
                $this->file($shop, $overrides);
                $this->fail("Expected {$rule}.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame($rule, $violation->rule);
            }
        }

        $this->assertSame(0, Complaint::query()->count());

        for ($i = 0; $i < ComplaintService::MAX_OPEN_PER_CUSTOMER; $i++) {
            $this->file();
        }

        try {
            $this->file();
            $this->fail('The sixth open complaint must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('complaint.too_many_open', $violation->rule);
        }
    }

    public function test_a_complaint_runs_from_assignment_to_resolution_and_is_closed_by_the_customer(): void
    {
        Notification::fake();
        $complaint = $this->file();

        $this->complaints->assign($this->admin, $complaint, $this->colleague);
        $this->assertSame(ComplaintStatus::Assigned, $complaint->status);
        $this->assertSame($this->colleague->getKey(), $complaint->assigned_to_user_id);
        Notification::assertSentTo($this->colleague, SystemNotice::class);
        Notification::assertSentTo($this->customer, SystemNotice::class);

        $this->complaints->start($this->colleague, $complaint);
        $this->complaints->resolve($this->colleague, $complaint, 'The washroom was cleaned and the schedule changed.');
        $this->assertSame(ComplaintStatus::Resolved, $complaint->refresh()->status);
        $this->assertSame('The washroom was cleaned and the schedule changed.', $complaint->resolution_note);

        $this->complaints->confirm($this->customer, $complaint);
        $this->assertSame(ComplaintStatus::Closed, $complaint->refresh()->status);
        $this->assertNotNull($complaint->closed_at);

        $this->assertSame(['open', 'assigned', 'in_progress', 'resolved', 'closed'], $complaint->events->pluck('to_status')->all());
    }

    public function test_the_customer_can_reopen_a_resolved_complaint_with_a_note_or_withdraw_an_open_one(): void
    {
        $complaint = $this->file();
        $this->complaints->assign($this->admin, $complaint, $this->admin);
        $this->complaints->start($this->admin, $complaint);
        $this->complaints->resolve($this->admin, $complaint, 'Cleaned the washroom.');

        try {
            $this->complaints->reopen($this->customer, $complaint, 'x');
            $this->fail('A reopen note is required.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('complaint.note_required', $violation->rule);
        }

        $this->complaints->reopen($this->customer, $complaint, 'It is dirty again today.');
        $this->assertSame(ComplaintStatus::InProgress, $complaint->refresh()->status);

        $second = $this->file(null, ['subject' => 'Broken bench']);
        $this->complaints->withdraw($this->customer, $second);
        $this->assertSame(ComplaintStatus::Withdrawn, $second->refresh()->status);

        $this->expectException(BusinessRuleViolation::class);
        $this->complaints->start($this->admin, $second);
    }

    public function test_rejecting_needs_a_reason_and_is_final(): void
    {
        $complaint = $this->file();

        try {
            $this->complaints->reject($this->admin, $complaint, 'no');
            $this->fail('A reason is required.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('complaint.note_required', $violation->rule);
        }

        $this->complaints->reject($this->admin, $complaint, 'This is not something the mall controls.');
        $this->assertSame(ComplaintStatus::Rejected, $complaint->refresh()->status);

        $this->expectException(BusinessRuleViolation::class);
        $this->complaints->assign($this->admin, $complaint, $this->colleague);
    }

    public function test_steps_cannot_be_skipped_and_resolving_needs_a_note(): void
    {
        $complaint = $this->file();

        foreach ([
            fn () => $this->complaints->resolve($this->admin, $complaint, 'Done quickly.'),
            fn () => $this->complaints->start($this->admin, $complaint),
            fn () => $this->complaints->confirm($this->customer, $complaint),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A step must not be skipped.');
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('complaint.invalid_transition', $violation->rule);
            }
        }

        $this->complaints->assign($this->admin, $complaint, $this->admin);
        $this->complaints->start($this->admin, $complaint);

        try {
            $this->complaints->resolve($this->admin, $complaint, '  ');
            $this->fail('A resolution note is required.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('complaint.note_required', $violation->rule);
        }

        $this->assertSame(ComplaintStatus::InProgress, $complaint->refresh()->status);
    }

    public function test_a_complaint_can_only_be_assigned_to_an_administrator_of_its_mall(): void
    {
        $complaint = $this->file();
        $stranger = $this->administratorOf(Mall::factory()->create());
        $shopOwner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($shopOwner, Shop::factory()->create(['mall_id' => $this->mall->getKey()]), RoleCode::ShopOwner);

        foreach ([$stranger, $shopOwner, $this->customer] as $candidate) {
            try {
                $this->complaints->assign($this->admin, $complaint, $candidate);
                $this->fail('Only administrators of this mall may be assigned.');
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('complaint.assignee_not_administrator', $violation->rule);
            }
        }

        $this->assertNull($complaint->refresh()->assigned_to_user_id);
    }

    public function test_only_the_malls_administrators_may_work_a_complaint(): void
    {
        $complaint = $this->file();

        foreach ([$this->customer, $this->administratorOf(Mall::factory()->create())] as $intruder) {
            try {
                $this->complaints->assign($intruder, $complaint, $this->admin);
                $this->fail('Only administrators of the mall may assign.');
            } catch (AuthorizationException) {
                $this->assertSame(ComplaintStatus::Open, $complaint->refresh()->status);
            }
        }
    }

    public function test_history_is_append_only_and_what_was_reported_cannot_change(): void
    {
        $complaint = $this->file();

        try {
            ComplaintEvent::query()->firstOrFail()->update(['note' => 'edited']);
            $this->fail('History must be final.');
        } catch (LogicException) {
            $this->assertNull(ComplaintEvent::query()->firstOrFail()->note);
        }

        foreach ([['subject' => 'Changed'], ['description' => 'Changed text here.'], ['customer_user_id' => $this->admin->getKey()]] as $change) {
            try {
                $complaint->update($change);
                $this->fail('A filed complaint is frozen.');
            } catch (LogicException) {
                $this->assertSame('Dirty washroom', $complaint->refresh()->subject);
            }
        }

        $this->expectException(LogicException::class);
        $complaint->delete();
    }

    public function test_the_customer_screens_file_show_and_close_a_complaint_and_hide_it_from_others(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $other = User::factory()->create();

        $this->get(route('account.complaints.index'))->assertRedirect(route('login'));
        $this->actingAs($this->customer)->get(route('account.complaints.create'))->assertOk()->assertSee($this->mall->name);

        $this->actingAs($this->customer)->post(route('account.complaints.store'), ['mall_id' => $this->mall->getKey(), 'shop_id' => $shop->getKey(), ...$this->data()])->assertRedirect();
        $complaint = Complaint::query()->sole();

        $this->actingAs($this->customer)->get(route('account.complaints.index'))->assertOk()->assertSee('Dirty washroom');
        $this->actingAs($this->customer)->get(route('account.complaints.show', $complaint))->assertOk()->assertSee($complaint->complaint_number)->assertSee($shop->name);

        $this->actingAs($other)->get(route('account.complaints.show', $complaint))->assertNotFound();
        $this->actingAs($other)->post(route('account.complaints.withdraw', $complaint))->assertNotFound();
        $this->assertSame(ComplaintStatus::Open, $complaint->refresh()->status);

        $this->actingAs($this->customer)->post(route('account.complaints.withdraw', $complaint))->assertRedirect();
        $this->assertSame(ComplaintStatus::Withdrawn, $complaint->refresh()->status);
        $this->actingAs($this->customer)->post(route('account.complaints.store'), ['mall_id' => $this->mall->getKey(), ...$this->data(['subject' => ''])])->assertSessionHasErrors('subject');
        $this->assertSame(1, Complaint::query()->count());
    }

    public function test_the_admin_screens_assign_work_resolve_and_reject_and_other_malls_are_kept_out(): void
    {
        $complaint = $this->file();
        $second = $this->file(null, ['subject' => 'Loud music']);
        $otherMall = Mall::factory()->create();
        $otherAdmin = $this->administratorOf($otherMall);

        $this->actingAs($this->admin)->get(route('mall-admin.complaints.index', $this->mall))->assertOk()->assertSee('Dirty washroom')->assertSee('Loud music');
        $this->actingAs($this->admin)->get(route('mall-admin.complaints.show', [$this->mall, $complaint]))->assertOk()->assertSee($complaint->complaint_number);
        $this->actingAs($this->admin)->get(route('mall-admin.complaints.index', [$this->mall, 'status' => 'nonsense']))->assertSessionHasErrors('status');

        $this->actingAs($this->admin)->post(route('mall-admin.complaints.assign', [$this->mall, $complaint]), ['assignee' => $this->colleague->getKey()])->assertRedirect();
        $this->actingAs($this->colleague)->get(route('mall-admin.complaints.index', [$this->mall, 'mine' => 1]))->assertSee('Dirty washroom')->assertDontSee('Loud music');
        $this->actingAs($this->colleague)->post(route('mall-admin.complaints.start', [$this->mall, $complaint]))->assertRedirect();
        $this->actingAs($this->colleague)->post(route('mall-admin.complaints.resolve', [$this->mall, $complaint]), ['note' => ''])->assertSessionHasErrors('business');
        $this->actingAs($this->colleague)->post(route('mall-admin.complaints.resolve', [$this->mall, $complaint]), ['note' => 'Cleaned and scheduled twice a day.'])->assertRedirect();
        $this->assertSame(ComplaintStatus::Resolved, $complaint->refresh()->status);

        $this->actingAs($this->admin)->post(route('mall-admin.complaints.reject', [$this->mall, $second]), ['reason' => 'Music is allowed at the weekend fair.'])->assertRedirect();
        $this->assertSame(ComplaintStatus::Rejected, $second->refresh()->status);

        $this->actingAs($otherAdmin)->get(route('mall-admin.complaints.show', [$otherMall, $complaint]))->assertNotFound();
        $this->actingAs($otherAdmin)->get(route('mall-admin.complaints.index', $this->mall))->assertForbidden();
        $this->actingAs($this->customer)->get(route('mall-admin.complaints.index', $this->mall))->assertForbidden();
        $this->actingAs($this->customer)->post(route('mall-admin.complaints.reject', [$this->mall, $complaint]), ['reason' => 'Because I say so.'])->assertForbidden();
    }
}
