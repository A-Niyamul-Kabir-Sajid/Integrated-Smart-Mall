<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Enums\SupportKind;
use App\Modules\ShopOwnerOperations\Enums\SupportStatus;
use App\Modules\ShopOwnerOperations\Models\SupportMessage;
use App\Modules\ShopOwnerOperations\Models\SupportThread;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Customer support conversations with a shop (Jira ISM-107/ISM-109, decision D-014).
 *
 * A signed-in customer starts a thread with a listed shop; staff with the support area
 * (cashiers, managers, owners of an active shop) answer; either side may close it. Messages
 * are append-only and a closed thread takes no more. Each side is told when the other writes.
 */
class SupportService
{
    public const MAX_OPEN_PER_CUSTOMER = 10;

    public const MAX_MESSAGES = 200;

    public const BODY_MAX = 2000;

    public function __construct(
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{subject: string, kind: string, body: string}  $data
     */
    public function open(User $customer, Shop $shop, array $data): SupportThread
    {
        if (! $customer->isActive()) {
            throw new AuthorizationException;
        }

        if (! $shop->status->isListed()) {
            throw new BusinessRuleViolation('support.shop_unavailable', __('This shop cannot be contacted right now.'));
        }

        $kind = SupportKind::tryFrom((string) ($data['kind'] ?? ''))
            ?? throw new BusinessRuleViolation('support.invalid_kind', __('Choose question or complaint.'));

        $subject = trim((string) ($data['subject'] ?? ''));

        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
            throw new BusinessRuleViolation('support.invalid_subject', __('Give the message a subject of 3 to 150 characters.'));
        }

        $body = $this->body($data['body'] ?? '');

        $open = SupportThread::query()->where('customer_user_id', $customer->getKey())->whereIn('status', [SupportStatus::Open->value, SupportStatus::Answered->value])->count();

        if ($open >= self::MAX_OPEN_PER_CUSTOMER) {
            throw new BusinessRuleViolation('support.too_many_open', __('You already have :max open conversations. Close some first.', ['max' => self::MAX_OPEN_PER_CUSTOMER]));
        }

        $thread = DB::transaction(function () use ($customer, $shop, $kind, $subject, $body): SupportThread {
            $thread = SupportThread::query()->create([
                'shop_id' => $shop->getKey(),
                'customer_user_id' => $customer->getKey(),
                'subject' => $subject,
                'kind' => $kind,
                'status' => SupportStatus::Open,
                'last_message_at' => now(),
            ]);

            $this->addMessage($thread, $customer, 'customer', $body);
            $this->audit->record('support.opened', $thread, ['kind' => $kind->value], $shop->mall_id, $shop->getKey());

            return $thread;
        });

        foreach ($this->memberships->shopOwners($shop) as $owner) {
            $owner->notify(new SystemNotice(__('New customer message'), $subject, route('workspace.support.show', [$shop, $thread])));
        }

        return $thread;
    }

    public function customerReply(User $customer, SupportThread $thread, string $body): SupportThread
    {
        if ($thread->customer_user_id !== $customer->getKey()) {
            throw new AuthorizationException;
        }

        $body = $this->body($body);

        $this->post($thread, $customer, 'customer', $body, SupportStatus::Open);

        foreach ($this->memberships->shopOwners($thread->loadMissing('shop')->shop) as $owner) {
            $owner->notify(new SystemNotice(__('Customer replied'), $thread->subject, route('workspace.support.show', [$thread->shop_id, $thread])));
        }

        return $thread;
    }

    public function staffReply(User $staff, SupportThread $thread, string $body): SupportThread
    {
        Gate::forUser($staff)->authorize(ShopArea::Support->ability(), $thread->loadMissing('shop')->shop);

        $body = $this->body($body);

        $this->post($thread, $staff, 'shop', $body, SupportStatus::Answered);
        $thread->loadMissing('customer')->customer->notify(new SystemNotice(__('The shop replied'), $thread->subject, route('support.show', $thread)));

        return $thread;
    }

    /**
     * Either side closes the conversation; a closed thread takes no more messages.
     */
    public function close(User $actor, SupportThread $thread): SupportThread
    {
        if ($thread->customer_user_id !== $actor->getKey()) {
            Gate::forUser($actor)->authorize(ShopArea::Support->ability(), $thread->loadMissing('shop')->shop);
        }

        return DB::transaction(function () use ($thread): SupportThread {
            $locked = SupportThread::query()->with('shop')->lockForUpdate()->findOrFail($thread->getKey());

            if ($locked->status === SupportStatus::Closed) {
                throw new BusinessRuleViolation('support.closed', __('This conversation is already closed.'));
            }

            $locked->update(['status' => SupportStatus::Closed, 'closed_at' => now()]);
            $this->audit->record('support.closed', $locked, [], $locked->shop->mall_id, $locked->shop_id);
            $thread->setRawAttributes($locked->getAttributes(), true);

            return $thread;
        });
    }

    /**
     * @return LengthAwarePaginator<int, SupportThread>
     */
    public function listForShop(User $staff, Shop $shop, ?SupportStatus $status = null, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($staff)->authorize(ShopArea::Support->ability(), $shop);

        return SupportThread::query()->where('shop_id', $shop->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->with('customer:user_id,display_name')
            ->orderByRaw("case status when 'open' then 0 when 'answered' then 1 else 2 end")
            ->latest('last_message_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, SupportThread>
     */
    public function listForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator
    {
        return SupportThread::query()->where('customer_user_id', $customer->getKey())
            ->with('shop:shop_id,name')
            ->latest('last_message_at')
            ->paginate($perPage);
    }

    private function post(SupportThread $thread, User $author, string $side, string $body, SupportStatus $then): void
    {
        DB::transaction(function () use ($thread, $author, $side, $body, $then): void {
            $locked = SupportThread::query()->lockForUpdate()->findOrFail($thread->getKey());

            if ($locked->status === SupportStatus::Closed) {
                throw new BusinessRuleViolation('support.closed', __('This conversation is closed. Start a new one if you need more help.'));
            }

            if ($locked->messages()->count() >= self::MAX_MESSAGES) {
                throw new BusinessRuleViolation('support.too_long', __('This conversation is too long. Close it and start a new one.'));
            }

            $this->addMessage($locked, $author, $side, $body);
            $locked->update(['status' => $then, 'last_message_at' => now()]);
            $thread->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function addMessage(SupportThread $thread, User $author, string $side, string $body): void
    {
        SupportMessage::query()->create([
            'support_thread_id' => $thread->getKey(),
            'author_user_id' => $author->getKey(),
            'author_side' => $side,
            'body' => $body,
            'created_at' => now(),
        ]);
    }

    private function body(mixed $body): string
    {
        $body = trim((string) $body);

        if ($body === '' || mb_strlen($body) > self::BODY_MAX) {
            throw new BusinessRuleViolation('support.invalid_body', __('Write a message of up to :max characters.', ['max' => self::BODY_MAX]));
        }

        return $body;
    }
}
