<?php

namespace Tests\Feature\Modules\MallOwnerAdmin;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Enums\AnnouncementStatus;
use App\Modules\MallOwnerAdmin\Models\Announcement;
use App\Modules\MallOwnerAdmin\Services\AnnouncementService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    private AnnouncementService $announcements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['timezone' => 'Asia/Dhaka']);
        $this->admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($this->admin, $this->mall, RoleCode::MallAdmin);
        $this->announcements = app(AnnouncementService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function notice(array $overrides = []): array
    {
        return ['kind' => 'notice', 'title' => 'Parking lot closed', 'body' => 'Lot B is closed on Friday.', ...$overrides];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(array $overrides = []): array
    {
        return ['kind' => 'event', 'title' => 'Eid fair', 'body' => 'Music and food.', 'location_note' => 'Atrium', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), ...$overrides];
    }

    public function test_a_draft_is_hidden_until_it_is_published_and_hidden_again_when_unpublished_or_archived(): void
    {
        $announcement = $this->announcements->create($this->admin, $this->mall, $this->notice());

        $this->assertSame(AnnouncementStatus::Draft, $announcement->status);
        $this->assertCount(0, $this->announcements->publicFor($this->mall)['notices']);

        $this->announcements->publish($this->admin, $announcement);
        $this->assertCount(1, $this->announcements->publicFor($this->mall)['notices']);

        $this->announcements->unpublish($this->admin, $announcement);
        $this->assertCount(0, $this->announcements->publicFor($this->mall)['notices']);

        $this->announcements->publish($this->admin, $announcement);
        $this->announcements->archive($this->admin, $announcement);
        $this->assertCount(0, $this->announcements->publicFor($this->mall)['notices']);

        $this->expectException(BusinessRuleViolation::class);
        $this->announcements->publish($this->admin, $announcement);
    }

    public function test_an_archived_announcement_cannot_be_edited(): void
    {
        $announcement = $this->announcements->create($this->admin, $this->mall, $this->notice());
        $this->announcements->archive($this->admin, $announcement);

        $this->expectException(BusinessRuleViolation::class);
        $this->announcements->update($this->admin, $announcement, $this->notice(['title' => 'Changed']));
    }

    public function test_an_event_needs_a_start_and_gets_a_default_end_and_times_are_read_in_the_mall_time_zone(): void
    {
        try {
            $this->announcements->create($this->admin, $this->mall, $this->event(['starts_at' => null]));
            $this->fail('An event without a start must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('announcement.event_needs_start', $violation->rule);
        }

        $event = $this->announcements->create($this->admin, $this->mall, $this->event(['starts_at' => '2030-12-01T18:00']));

        $this->assertSame('2030-12-01 12:00:00', $event->starts_at->format('Y-m-d H:i:s'), 'Dhaka is UTC+6.');
        $this->assertSame('2030-12-01 15:00:00', $event->ends_at->format('Y-m-d H:i:s'), 'An event without an end runs for three hours.');
        $this->assertSame('Atrium', $event->location_note);
    }

    public function test_the_end_must_follow_the_start_and_text_is_validated(): void
    {
        foreach ([
            ['rule' => 'announcement.invalid_window', 'data' => $this->event(['starts_at' => '2030-12-01T18:00', 'ends_at' => '2030-12-01T17:00'])],
            ['rule' => 'announcement.invalid_title', 'data' => $this->notice(['title' => 'ab'])],
            ['rule' => 'announcement.invalid_body', 'data' => $this->notice(['body' => 'abc'])],
            ['rule' => 'announcement.invalid_kind', 'data' => $this->notice(['kind' => 'advert'])],
            ['rule' => 'announcement.invalid_time', 'data' => $this->notice(['starts_at' => 'not a date'])],
        ] as $case) {
            try {
                $this->announcements->create($this->admin, $this->mall, $case['data']);
                $this->fail("Expected {$case['rule']}.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame($case['rule'], $violation->rule);
            }
        }

        $this->assertSame(0, Announcement::query()->count());
    }

    public function test_customers_see_only_published_announcements_that_are_in_their_window(): void
    {
        $this->announcements->create($this->admin, $this->mall, $this->notice(['title' => 'Visible notice']), true);
        $this->announcements->create($this->admin, $this->mall, $this->notice(['title' => 'Future notice', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i')]), true);
        $this->announcements->create($this->admin, $this->mall, $this->notice(['title' => 'Draft notice']));
        $this->announcements->create($this->admin, $this->mall, $this->event(['title' => 'Upcoming event']), true);
        $ended = $this->announcements->create($this->admin, $this->mall, $this->notice(['title' => 'Old notice']), true);
        $ended->update(['ends_at' => now()->subHour()]);

        $public = $this->announcements->publicFor($this->mall);

        $this->assertSame(['Visible notice'], $public['notices']->pluck('title')->all());
        $this->assertSame(['Upcoming event'], $public['events']->pluck('title')->all());
    }

    public function test_publishing_something_that_already_ended_is_refused(): void
    {
        $announcement = $this->announcements->create($this->admin, $this->mall, $this->notice());
        $announcement->update(['ends_at' => now()->subHour()]);

        try {
            $this->announcements->publish($this->admin, $announcement);
            $this->fail('An ended announcement must not be published.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('announcement.already_over', $violation->rule);
        }
    }

    public function test_the_public_page_and_the_mall_home_show_published_news_without_signing_in(): void
    {
        $this->announcements->create($this->admin, $this->mall, $this->notice(['title' => 'Lot B closed']), true);
        $this->announcements->create($this->admin, $this->mall, $this->event(['title' => 'Eid fair']), true);
        $this->announcements->create($this->admin, $this->mall, $this->notice(['title' => 'Secret draft']));
        $other = Mall::factory()->create();
        app(AnnouncementService::class)->create($this->adminOf($other), $other, $this->notice(['title' => 'Other mall news']), true);

        $this->get(route('announcements.index', $this->mall->slug))->assertOk()->assertSee('Lot B closed')->assertSee('Eid fair')->assertSee('Atrium')->assertDontSee('Secret draft')->assertDontSee('Other mall news');
        $this->get(route('malls.show', $this->mall->slug))->assertOk()->assertSee('News and events')->assertSee('Lot B closed')->assertDontSee('Secret draft');
    }

    public function test_the_screens_create_edit_publish_and_archive(): void
    {
        $this->actingAs($this->admin)->get(route('mall-admin.announcements.create', $this->mall))->assertOk();

        $this->actingAs($this->admin)->post(route('mall-admin.announcements.store', $this->mall), $this->notice(['publish' => 1]))->assertRedirect(route('mall-admin.announcements.index', $this->mall));
        $announcement = Announcement::query()->sole();
        $this->assertSame(AnnouncementStatus::Published, $announcement->status);

        $this->actingAs($this->admin)->get(route('mall-admin.announcements.index', $this->mall))->assertOk()->assertSee('Parking lot closed');
        $this->actingAs($this->admin)->get(route('mall-admin.announcements.edit', [$this->mall, $announcement]))->assertOk()->assertSee('Parking lot closed');
        $this->actingAs($this->admin)->put(route('mall-admin.announcements.update', [$this->mall, $announcement]), $this->notice(['title' => 'Parking lot reopened']))->assertRedirect();
        $this->assertSame('Parking lot reopened', $announcement->refresh()->title);

        $this->actingAs($this->admin)->post(route('mall-admin.announcements.unpublish', [$this->mall, $announcement]))->assertRedirect();
        $this->actingAs($this->admin)->post(route('mall-admin.announcements.archive', [$this->mall, $announcement]))->assertRedirect();
        $this->assertSame(AnnouncementStatus::Archived, $announcement->refresh()->status);

        $this->actingAs($this->admin)->post(route('mall-admin.announcements.publish', [$this->mall, $announcement]))->assertSessionHasErrors('business');
        $this->actingAs($this->admin)->post(route('mall-admin.announcements.store', $this->mall), $this->notice(['title' => '']))->assertSessionHasErrors('title');
        $this->assertSame(1, Announcement::query()->count());
        $this->assertSame(4, AuditLog::query()->where('action', 'like', 'announcement.%')->where('mall_id', $this->mall->getKey())->count());
    }

    public function test_other_administrators_customers_and_shop_staff_are_refused_and_other_malls_records_are_not_found(): void
    {
        $announcement = $this->announcements->create($this->admin, $this->mall, $this->notice());
        $otherMall = Mall::factory()->create();
        $otherAdmin = $this->adminOf($otherMall);
        $customer = User::factory()->create();

        $this->get(route('mall-admin.announcements.index', $this->mall))->assertRedirect(route('login'));

        foreach ([$customer, $otherAdmin] as $intruder) {
            $this->actingAs($intruder)->get(route('mall-admin.announcements.index', $this->mall))->assertForbidden();
            $this->actingAs($intruder)->post(route('mall-admin.announcements.publish', [$this->mall, $announcement]))->assertForbidden();
        }

        $this->actingAs($otherAdmin)->get(route('mall-admin.announcements.edit', [$otherMall, $announcement]))->assertNotFound();
        $this->actingAs($otherAdmin)->post(route('mall-admin.announcements.publish', [$otherMall, $announcement]))->assertNotFound();
        $this->assertSame(AnnouncementStatus::Draft, $announcement->refresh()->status);

        $this->expectException(AuthorizationException::class);
        $this->announcements->publish($otherAdmin, $announcement);
    }

    public function test_announcements_are_never_deleted_and_never_move_to_another_mall(): void
    {
        $announcement = $this->announcements->create($this->admin, $this->mall, $this->notice());

        try {
            $announcement->delete();
            $this->fail('Deleting must be refused.');
        } catch (LogicException) {
            $this->assertSame(1, Announcement::query()->count());
        }

        $this->expectException(LogicException::class);
        $announcement->update(['mall_id' => Mall::factory()->create()->getKey()]);
    }

    private function adminOf(Mall $mall): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantMallRole($user, $mall, RoleCode::MallAdmin);

        return $user;
    }
}
