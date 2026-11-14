<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Enums\ComplaintCategory;
use App\Modules\MallOwnerAdmin\Enums\ComplaintStatus;
use App\Modules\MallOwnerAdmin\Models\Complaint;
use App\Modules\MallOwnerAdmin\Models\ComplaintEvent;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Customer complaints to mall management (Jira ISM-116/ISM-117, decision D-015).
 *
 * A signed-in customer files a complaint about the mall or one of its shops. The mall's
 * administrators assign it to one of themselves, work it, and resolve or reject it with a note;
 * the customer confirms (closes) or reopens a resolved complaint, or withdraws it earlier. Every
 * step is kept in an append-only history and the customer sees the notes.
 */
class ComplaintService
{
    public const MAX_OPEN_PER_CUSTOMER = 5;

    public const NOTE_MIN = 3;

    public const REASON_MIN = 5;

    public function __construct(
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{category: string, subject: string, description: string}  $data
     */
    public function file(User $customer, Mall $mall, ?Shop $shop, array $data): Complaint
    {
        $category = ComplaintCategory::tryFrom((string) ($data['category'] ?? ''))
            ?? throw new BusinessRuleViolation('complaint.invalid_category', __('Choose what the complaint is about.'));

        $subject = trim((string) ($data['subject'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
            throw new BusinessRuleViolation('complaint.invalid_subject', __('Give the complaint a subject of 3 to 150 characters.'));
        }

        if (mb_strlen($description) < 10 || mb_strlen($description) > 2000) {
            throw new BusinessRuleViolation('complaint.invalid_description', __('Describe what happened in 10 to 2000 characters.'));
        }

        if ($shop !== null && ($shop->mall_id !== $mall->getKey() || ! $shop->status->isListed())) {
            throw new BusinessRuleViolation('complaint.invalid_shop', __('Choose a shop of this mall.'));
        }

        $open = Complaint::query()->where('customer_user_id', $customer->getKey())->whereIn('status', $this->openValues())->count();

        if ($open >= self::MAX_OPEN_PER_CUSTOMER) {
            throw new BusinessRuleViolation('complaint.too_many_open', __('You already have :max open complaints. Please wait until some are resolved.', ['max' => self::MAX_OPEN_PER_CUSTOMER]));
        }

        $complaint = DB::transaction(function () use ($customer, $mall, $shop, $category, $subject, $description): Complaint {
            $complaint = $this->createComplaint([
                'mall_id' => $mall->getKey(),
                'customer_user_id' => $customer->getKey(),
                'shop_id' => $shop?->getKey(),
                'category' => $category,
                'subject' => $subject,
                'description' => $description,
                'status' => ComplaintStatus::Open,
            ]);

            $this->recordEvent($complaint, null, ComplaintStatus::Open, $customer, null);
            $this->audit->record('complaint.filed', $complaint, ['number' => $complaint->complaint_number, 'category' => $category->value], $mall->getKey(), $shop?->getKey());

            return $complaint;
        });

        foreach ($this->memberships->mallAdministrators($mall) as $administrator) {
            $administrator->notify(new SystemNotice(__('New complaint :number', ['number' => $complaint->complaint_number]), $subject, route('mall-admin.complaints.show', [$mall, $complaint])));
        }

        return $complaint;
    }

    /**
     * The customer's own complaints, newest first.
     *
     * @return Collection<int, Complaint>
     */
    public function listForCustomer(User $customer): Collection
    {
        return Complaint::query()->where('customer_user_id', $customer->getKey())->with('shop:shop_id,name')->latest('created_at')->get();
    }

    public function withdraw(User $customer, Complaint $complaint): Complaint
    {
        $this->authorizeCustomer($customer, $complaint);

        return $this->move($complaint, ComplaintStatus::Withdrawn, $customer, null, ['closed_at' => now()]);
    }

    public function confirm(User $customer, Complaint $complaint): Complaint
    {
        $this->authorizeCustomer($customer, $complaint);

        return $this->move($complaint, ComplaintStatus::Closed, $customer, null, ['closed_at' => now()]);
    }

    public function reopen(User $customer, Complaint $complaint, string $note): Complaint
    {
        $this->authorizeCustomer($customer, $complaint);

        $note = $this->note($note, self::NOTE_MIN, 500, __('Say what is still wrong, in 3 to 500 characters.'));

        $this->move($complaint, ComplaintStatus::InProgress, $customer, $note);

        $this->tellAssignee($complaint, __('Complaint :number was reopened', ['number' => $complaint->complaint_number]), $note);

        return $complaint;
    }

    /**
     * Complaints of the mall for its administrators, open ones first.
     *
     * @param  array{status?: string|null, mine?: bool|null}  $filters
     * @return LengthAwarePaginator<int, Complaint>
     */
    public function listForMall(User $administrator, Mall $mall, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $this->authorizeMall($administrator, $mall);

        $status = ComplaintStatus::tryFrom((string) ($filters['status'] ?? ''));

        return Complaint::query()->where('mall_id', $mall->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when(! empty($filters['mine']), fn ($query) => $query->where('assigned_to_user_id', $administrator->getKey()))
            ->with(['shop:shop_id,name', 'customer:user_id,display_name', 'assignee:user_id,display_name'])
            ->orderByRaw("case when status in ('open', 'assigned', 'in_progress') then 0 else 1 end")
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Gives the complaint to one of the mall's administrators (also used to reassign it).
     */
    public function assign(User $administrator, Complaint $complaint, User $assignee): Complaint
    {
        $this->authorizeMall($administrator, $complaint->loadMissing('mall')->mall);

        if (! $this->memberships->mallAdministrators($complaint->mall)->contains(fn (User $candidate): bool => $candidate->is($assignee))) {
            throw new BusinessRuleViolation('complaint.assignee_not_administrator', __('A complaint can only be assigned to an administrator of this mall.'));
        }

        $this->move($complaint, ComplaintStatus::Assigned, $administrator, __('Assigned to :name', ['name' => $assignee->display_name]), ['assigned_to_user_id' => $assignee->getKey()]);

        if (! $assignee->is($administrator)) {
            $assignee->notify(new SystemNotice(__('Complaint :number was assigned to you', ['number' => $complaint->complaint_number]), $complaint->subject, route('mall-admin.complaints.show', [$complaint->mall_id, $complaint])));
        }

        $this->tellCustomer($complaint, __('Your complaint was taken up'), __(':number is now with :name.', ['number' => $complaint->complaint_number, 'name' => $assignee->display_name]));

        return $complaint;
    }

    public function start(User $administrator, Complaint $complaint): Complaint
    {
        $this->authorizeMall($administrator, $complaint->loadMissing('mall')->mall);

        $this->move($complaint, ComplaintStatus::InProgress, $administrator, null);
        $this->tellCustomer($complaint, __('Your complaint is being looked into'), __(':number is now being looked into.', ['number' => $complaint->complaint_number]));

        return $complaint;
    }

    public function resolve(User $administrator, Complaint $complaint, string $note): Complaint
    {
        $this->authorizeMall($administrator, $complaint->loadMissing('mall')->mall);

        $note = $this->note($note, self::NOTE_MIN, 1000, __('Say what was done, in 3 to 1000 characters.'));

        $this->move($complaint, ComplaintStatus::Resolved, $administrator, $note, ['resolution_note' => $note]);
        $this->tellCustomer($complaint, __('Your complaint was resolved'), $note);

        return $complaint;
    }

    public function reject(User $administrator, Complaint $complaint, string $reason): Complaint
    {
        $this->authorizeMall($administrator, $complaint->loadMissing('mall')->mall);

        $reason = $this->note($reason, self::REASON_MIN, 1000, __('Write a reason of :min to 1000 characters.', ['min' => self::REASON_MIN]));

        $this->move($complaint, ComplaintStatus::Rejected, $administrator, $reason, ['resolution_note' => $reason, 'closed_at' => now()]);
        $this->tellCustomer($complaint, __('Your complaint was not accepted'), $reason);

        return $complaint;
    }

    /**
     * @return list<string>
     */
    private function openValues(): array
    {
        return array_map(fn (ComplaintStatus $status): string => $status->value, ComplaintStatus::openCases());
    }

    private function note(string $note, int $min, int $max, string $message): string
    {
        $note = trim($note);

        if (mb_strlen($note) < $min || mb_strlen($note) > $max) {
            throw new BusinessRuleViolation('complaint.note_required', $message);
        }

        return $note;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createComplaint(array $attributes): Complaint
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                // A savepoint, so a clash on the number does not abort an enclosing PostgreSQL transaction.
                return DB::transaction(fn (): Complaint => Complaint::query()->create([...$attributes, 'complaint_number' => 'CMP-'.Str::upper(Str::random(8))]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not allocate a unique complaint number.');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function move(Complaint $complaint, ComplaintStatus $to, User $actor, ?string $note, array $extra = []): Complaint
    {
        return DB::transaction(function () use ($complaint, $to, $actor, $note, $extra): Complaint {
            $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->getKey());
            $from = $locked->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new BusinessRuleViolation('complaint.invalid_transition', __('A :from complaint cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
            }

            $locked->update([...$extra, 'status' => $to]);
            $this->recordEvent($locked, $from, $to, $actor, $note);
            $this->audit->record('complaint.'.$to->value, $locked, array_filter(['number' => $locked->complaint_number, 'note' => $note]), $locked->mall_id, $locked->shop_id);

            $complaint->setRawAttributes($locked->getAttributes(), true);

            return $complaint;
        });
    }

    private function recordEvent(Complaint $complaint, ?ComplaintStatus $from, ComplaintStatus $to, ?User $actor, ?string $note): void
    {
        ComplaintEvent::query()->create([
            'complaint_id' => $complaint->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->getKey(),
            'note' => $note === null ? null : mb_substr($note, 0, 1000),
            'created_at' => now(),
        ]);
    }

    private function authorizeCustomer(User $customer, Complaint $complaint): void
    {
        abort_unless($complaint->customer_user_id === $customer->getKey(), 404);
    }

    private function authorizeMall(User $administrator, Mall $mall): void
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);
    }

    private function tellCustomer(Complaint $complaint, string $title, string $body): void
    {
        $complaint->loadMissing('customer')->customer?->notify(new SystemNotice($title, $body, route('account.complaints.show', $complaint)));
    }

    private function tellAssignee(Complaint $complaint, string $title, string $body): void
    {
        $complaint->loadMissing('assignee')->assignee?->notify(new SystemNotice($title, $body, route('mall-admin.complaints.show', [$complaint->mall_id, $complaint])));
    }
}
