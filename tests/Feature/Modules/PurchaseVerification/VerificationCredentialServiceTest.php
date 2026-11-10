<?php

namespace Tests\Feature\Modules\PurchaseVerification;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use App\Modules\PurchaseVerification\Models\VerificationToken;
use App\Modules\PurchaseVerification\Services\CredentialSecrets;
use App\Modules\PurchaseVerification\Services\VerificationCredentialService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Tests\TestCase;

class VerificationCredentialServiceTest extends TestCase
{
    use CreatesPurchaseFixtures, RefreshDatabase;

    private function service(): VerificationCredentialService
    {
        return app(VerificationCredentialService::class);
    }

    /**
     * @return array{0: Shop, 1: User, 2: PurchaseItem}
     */
    private function completedLine(): array
    {
        $shop = Shop::factory()->create();
        $purchase = Purchase::factory()->for($shop)->withLine()->create();

        return [$shop, $this->staffOf($shop), $purchase->items()->first()];
    }

    public function test_staff_issue_one_unguessable_credential_for_a_purchase_line(): void
    {
        [, $staff, $item] = $this->completedLine();

        $issued = $this->service()->issueCredential($staff, $item);

        $this->assertMatchesRegularExpression('/^ismv_[A-Za-z0-9_-]{43}$/', $issued->secret);
        $this->assertSame($item->getKey(), $issued->token->purchase_item_id);
        $this->assertSame($staff->getKey(), $issued->token->issued_by_user_id);
        $this->assertNull($issued->token->revoked_at);
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $issued->token->expires_at->timestamp, 5);
        $this->assertSame(hash('sha256', $issued->secret), $issued->token->fresh()->getRawOriginal('token_digest'));
    }

    public function test_credentials_are_unique_and_the_secret_is_never_stored(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $secrets = [];

        foreach (Purchase::factory()->for($shop)->withLine()->count(25)->create() as $purchase) {
            $secrets[] = $this->service()->issueCredential($staff, $purchase->items()->first())->secret;
        }

        $this->assertCount(25, array_unique($secrets));
        $this->assertSame(25, VerificationToken::query()->distinct()->count('token_digest'));

        $stored = json_encode([DB::table('verification_tokens')->get(), DB::table('audit_logs')->get()]);

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
            $this->assertStringNotContainsString(substr($secret, strlen(CredentialSecrets::PREFIX)), $stored);
        }
    }

    public function test_the_secret_does_not_leak_through_serialisation_dumps_or_logs(): void
    {
        [, $staff, $item] = $this->completedLine();
        Log::spy();

        $issued = $this->service()->issueCredential($staff, $item);

        $this->assertStringNotContainsString($issued->secret, print_r($issued, true));
        $this->assertStringNotContainsString($issued->secret, serialize($issued));
        $this->assertStringNotContainsString($issued->secret, $issued->token->toJson());
        $this->assertArrayNotHasKey('token_digest', $issued->token->toArray());
        Log::shouldNotHaveReceived('info');
    }

    public function test_issuing_is_audited_without_the_secret(): void
    {
        [$shop, $staff, $item] = $this->completedLine();
        $this->actingAs($staff);

        $issued = $this->service()->issueCredential($staff, $item);

        $entry = AuditLog::query()->where('action', 'verification.credential_issued')->sole();
        $this->assertSame($staff->getKey(), $entry->actor_id);
        $this->assertSame($shop->getKey(), $entry->shop_id);
        $this->assertSame($issued->token->getKey(), $entry->subject_id);
        $this->assertSame($item->getKey(), $entry->metadata['purchase_item_id']);
        $this->assertStringNotContainsString($issued->secret, json_encode($entry->metadata));
    }

    public function test_a_purchase_line_gets_only_one_credential(): void
    {
        [, $staff, $item] = $this->completedLine();
        $this->service()->issueCredential($staff, $item);

        $this->assertRuleViolated('verification.already_issued', fn () => $this->service()->issueCredential($staff, $item));
        $this->assertSame(1, VerificationToken::query()->count());
    }

    public function test_the_database_refuses_a_second_credential_or_a_repeated_digest(): void
    {
        $token = VerificationToken::factory()->create();

        $this->expectException(QueryException::class);
        VerificationToken::factory()->create(['purchase_item_id' => $token->purchase_item_id]);
    }

    public function test_only_staff_of_the_purchase_shop_may_issue_credentials(): void
    {
        [, , $item] = $this->completedLine();
        $buyer = User::factory()->create();
        $otherShopStaff = $this->staffOf(Shop::factory()->create());

        foreach ([$buyer, $otherShopStaff] as $user) {
            try {
                $this->service()->issueCredential($user, $item);
                $this->fail('Expected the credential to be refused.');
            } catch (AuthorizationException) {
                $this->assertSame(0, VerificationToken::query()->count());
            }
        }
    }

    public function test_credentials_are_only_issued_for_completed_sales(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $voided = Purchase::factory()->for($shop)->voided()->withLine()->create();

        $this->assertRuleViolated('verification.purchase_not_completed', fn () => $this->service()->issueCredential($staff, $voided->items()->first()));
    }

    public function test_the_expiry_must_lie_between_now_and_one_year(): void
    {
        [, $staff, $item] = $this->completedLine();

        $this->assertRuleViolated('verification.invalid_expiry', fn () => $this->service()->issueCredential($staff, $item, now()->subMinute()));
        $this->assertRuleViolated('verification.invalid_expiry', fn () => $this->service()->issueCredential($staff, $item, now()->addDays(366)));

        $issued = $this->service()->issueCredential($staff, $item, now()->addDays(30));
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $issued->token->expires_at->timestamp, 5);
    }

    public function test_reissuing_rotates_the_secret_and_restarts_the_expiry(): void
    {
        [, $staff, $item] = $this->completedLine();
        $first = $this->service()->issueCredential($staff, $item, now()->addDays(10));
        $oldDigest = $first->token->fresh()->getRawOriginal('token_digest');

        $second = $this->service()->reissueCredential($staff, $first->token);

        $this->assertNotSame($first->secret, $second->secret);
        $this->assertSame(1, VerificationToken::query()->count());
        $token = $second->token->fresh();
        $this->assertSame($first->token->getKey(), $token->getKey());
        $this->assertNotSame($oldDigest, $token->getRawOriginal('token_digest'));
        $this->assertSame(hash('sha256', $second->secret), $token->getRawOriginal('token_digest'));
        $this->assertSame(0, VerificationToken::query()->where('token_digest', hash('sha256', $first->secret))->count());
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $token->expires_at->timestamp, 5);
        $this->assertSame(1, AuditLog::query()->where('action', 'verification.credential_reissued')->count());
    }

    public function test_reissuing_reactivates_a_revoked_credential_and_needs_shop_staff(): void
    {
        [, $staff, $item] = $this->completedLine();
        $issued = $this->service()->issueCredential($staff, $item);
        $this->service()->revokeCredential($staff, $issued->token);

        try {
            $this->service()->reissueCredential($this->staffOf(Shop::factory()->create()), $issued->token);
            $this->fail('Expected the reissue to be refused.');
        } catch (AuthorizationException) {
            $this->assertNotNull($issued->token->fresh()->revoked_at);
        }

        $this->service()->reissueCredential($staff, $issued->token);
        $this->assertNull($issued->token->fresh()->revoked_at);
    }

    public function test_revoking_invalidates_once_and_is_audited(): void
    {
        [, $staff, $item] = $this->completedLine();
        $issued = $this->service()->issueCredential($staff, $item);

        $this->service()->revokeCredential($staff, $issued->token);
        $firstRevocation = $issued->token->fresh()->revoked_at;
        $this->service()->revokeCredential($staff, $issued->token);

        $this->assertTrue($issued->token->fresh()->isRevoked());
        $this->assertEquals($firstRevocation, $issued->token->fresh()->revoked_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'verification.credential_revoked')->count());
    }

    public function test_revoking_needs_shop_staff(): void
    {
        [, $staff, $item] = $this->completedLine();
        $issued = $this->service()->issueCredential($staff, $item);

        $this->expectException(AuthorizationException::class);
        $this->service()->revokeCredential(User::factory()->create(), $issued->token);
    }

    public function test_a_credential_cannot_move_to_another_line_or_be_deleted(): void
    {
        $token = VerificationToken::factory()->create();

        foreach ([
            fn () => $token->update(['purchase_item_id' => PurchaseItem::factory()->create()->getKey()]),
            fn () => $token->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the change to be refused.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_the_secret_helper_recognises_only_well_formed_secrets(): void
    {
        $secrets = app(CredentialSecrets::class);
        $secret = $secrets->generate();

        $this->assertTrue($secrets->looksValid($secret));
        $this->assertTrue($secrets->looksValid("  {$secret}\n"));
        $this->assertFalse($secrets->looksValid(''));
        $this->assertFalse($secrets->looksValid('ismv_short'));
        $this->assertFalse($secrets->looksValid(substr($secret, 5)));
        $this->assertSame($secrets->digest($secret), $secrets->digest(" {$secret} "));
    }
}
