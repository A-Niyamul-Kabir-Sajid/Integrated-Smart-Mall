<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallOwnerAdmin\Enums\AnnouncementKind;
use App\Modules\MallOwnerAdmin\Enums\AnnouncementStatus;
use App\Modules\MallOwnerAdmin\Models\Announcement;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Mall-wide notices and events (Jira ISM-116/ISM-117, decision D-015).
 *
 * Administrators write an announcement as a draft, publish it, take it back to a draft or
 * archive it. Customers see published announcements that have not ended: a notice from its
 * start time (if any) until its end time (if any); an event from publication until it ends.
 * Times are entered in the mall's time zone and stored in UTC.
 */
class AnnouncementService
{
    public const PER_PAGE = 20;

    /** An event without an end time is shown for this long after it starts. */
    public const DEFAULT_EVENT_HOURS = 3;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{kind: string, title: string, body: string, location_note?: string|null, starts_at?: string|null, ends_at?: string|null}  $data
     */
    public function create(User $administrator, Mall $mall, array $data, bool $publish = false): Announcement
    {
        $this->authorize($administrator, $mall);

        $fields = $this->fields($mall, $data);

        return DB::transaction(function () use ($administrator, $mall, $fields, $publish): Announcement {
            $announcement = Announcement::query()->create([
                ...$fields,
                'mall_id' => $mall->getKey(),
                'status' => $publish ? AnnouncementStatus::Published : AnnouncementStatus::Draft,
                'published_at' => $publish ? now() : null,
                'created_by_user_id' => $administrator->getKey(),
            ]);

            $this->audit->record('announcement.created', $announcement, ['kind' => $announcement->kind->value, 'status' => $announcement->status->value], $mall->getKey());

            return $announcement;
        });
    }

    /**
     * @param  array{kind: string, title: string, body: string, location_note?: string|null, starts_at?: string|null, ends_at?: string|null}  $data
     */
    public function update(User $administrator, Announcement $announcement, array $data): Announcement
    {
        $this->authorize($administrator, $announcement->loadMissing('mall')->mall);

        if ($announcement->status === AnnouncementStatus::Archived) {
            throw new BusinessRuleViolation('announcement.archived', __('An archived announcement cannot be edited.'));
        }

        $announcement->update($this->fields($announcement->mall, $data));

        $this->audit->record('announcement.updated', $announcement, ['fields' => array_keys($announcement->getChanges())], $announcement->mall_id);

        return $announcement;
    }

    public function publish(User $administrator, Announcement $announcement): Announcement
    {
        return $this->move($administrator, $announcement, AnnouncementStatus::Published);
    }

    /**
     * Takes a published announcement back to a draft so it can be corrected.
     */
    public function unpublish(User $administrator, Announcement $announcement): Announcement
    {
        return $this->move($administrator, $announcement, AnnouncementStatus::Draft);
    }

    public function archive(User $administrator, Announcement $announcement): Announcement
    {
        return $this->move($administrator, $announcement, AnnouncementStatus::Archived);
    }

    /**
     * @param  array{status?: string|null}  $filters
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function listForMall(User $administrator, Mall $mall, array $filters = []): LengthAwarePaginator
    {
        $this->authorize($administrator, $mall);

        $status = AnnouncementStatus::tryFrom((string) ($filters['status'] ?? ''));

        return Announcement::query()->where('mall_id', $mall->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByRaw("case status when 'draft' then 0 when 'published' then 1 else 2 end")
            ->latest('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * What customers of the mall may read now: upcoming and running events soonest first, and
     * notices newest first.
     *
     * @return array{events: Collection<int, Announcement>, notices: Collection<int, Announcement>}
     */
    public function publicFor(Mall $mall, int $limit = 20): array
    {
        $visible = fn () => Announcement::query()->where('mall_id', $mall->getKey())->visibleNow();

        return [
            'events' => $visible()->where('kind', AnnouncementKind::Event->value)->orderBy('starts_at')->limit($limit)->get(),
            'notices' => $visible()->where('kind', AnnouncementKind::Notice->value)->orderByDesc('published_at')->limit($limit)->get(),
        ];
    }

    /**
     * @param  array{kind: string, title: string, body: string, location_note?: string|null, starts_at?: string|null, ends_at?: string|null}  $data
     * @return array<string, mixed>
     */
    private function fields(Mall $mall, array $data): array
    {
        $kind = AnnouncementKind::tryFrom((string) ($data['kind'] ?? ''))
            ?? throw new BusinessRuleViolation('announcement.invalid_kind', __('Choose notice or event.'));

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            throw new BusinessRuleViolation('announcement.invalid_title', __('Give the announcement a title of 3 to 150 characters.'));
        }

        if (mb_strlen($body) < 5 || mb_strlen($body) > 5000) {
            throw new BusinessRuleViolation('announcement.invalid_body', __('Write the announcement in 5 to 5000 characters.'));
        }

        $startsAt = filled($data['starts_at'] ?? null) ? $this->parse($mall, $data['starts_at']) : null;
        $endsAt = filled($data['ends_at'] ?? null) ? $this->parse($mall, $data['ends_at']) : null;

        if ($kind === AnnouncementKind::Event) {
            if ($startsAt === null) {
                throw new BusinessRuleViolation('announcement.event_needs_start', __('An event needs a start time.'));
            }

            $endsAt ??= $startsAt->addHours(self::DEFAULT_EVENT_HOURS);
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new BusinessRuleViolation('announcement.invalid_window', __('The end time must be after the start time.'));
        }

        return [
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'location_note' => $kind === AnnouncementKind::Event && filled($data['location_note'] ?? null) ? mb_substr(trim($data['location_note']), 0, 160) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    private function parse(Mall $mall, string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value, $mall->timezone)->utc();
        } catch (Throwable) {
            throw new BusinessRuleViolation('announcement.invalid_time', __('Enter a valid date and time.'));
        }
    }

    private function move(User $administrator, Announcement $announcement, AnnouncementStatus $to): Announcement
    {
        $this->authorize($administrator, $announcement->loadMissing('mall')->mall);

        $from = $announcement->status;

        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw new BusinessRuleViolation('announcement.invalid_transition', __('A :from announcement cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
        }

        if ($to === AnnouncementStatus::Published && $announcement->ends_at !== null && $announcement->ends_at->isPast()) {
            throw new BusinessRuleViolation('announcement.already_over', __('This announcement has already ended. Change its end time before publishing it.'));
        }

        $announcement->update(['status' => $to, 'published_at' => $to === AnnouncementStatus::Published ? now() : $announcement->published_at]);

        $this->audit->record('announcement.'.$to->value, $announcement, ['title' => $announcement->title], $announcement->mall_id);

        return $announcement;
    }

    private function authorize(User $administrator, Mall $mall): void
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);
    }
}
