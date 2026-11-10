<?php

namespace Tests\Feature\Modules\PurchaseVerification;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Contracts\PurchaseVerifier;
use App\Modules\PurchaseVerification\Enums\VerificationFailure;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use App\Modules\PurchaseVerification\Models\TokenRedemption;
use App\Modules\PurchaseVerification\Models\VerificationToken;
use App\Modules\PurchaseVerification\Services\CredentialSecrets;
use App\Modules\PurchaseVerification\Services\VerificationCredentialService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PurchaseVerifierTest extends TestCase
{
    use CreatesPurchaseFixtures, RefreshDatabase;

    private function verifier(): PurchaseVerifier
    {
        return app(PurchaseVerifier::class);
    }

    /**
     * Issues a credential on a one-line sale. A null buyer makes it a walk-in sale.
     *
     * @return array{0: string, 1: PurchaseItem, 2: VerificationToken, 3: Shop}
     */
    private function credentialFor(?User $buyer): array
    {
        $shop = Shop::factory()->create();
        $factory = Purchase::factory()->for($shop)->withLine();
        $purchase = ($buyer === null ? $factory : $factory->forBuyer($buyer))->create();
        $item = $purchase->items()->first();
        $issued = app(VerificationCredentialService::class)->issueCredential($this->staffOf($shop), $item);

        return [$issued->secret, $item, $issued->token, $shop];
    }

    public function test_the_buyer_can_check_and_then_redeem_the_credential_for_the_right_listing(): void
    {
        $buyer = User::factory()->create();
        [$secret, $item, $token, $shop] = $this->credentialFor($buyer);

        $check = $this->verifier()->checkEligibility($buyer->getKey(), $secret, $item->shop_product_id);

        $this->assertTrue($check->eligible);
        $this->assertNull($check->failure);
        $this->assertNull($check->redeemedAt);
        $this->assertSame($token->getKey(), $check->verificationTokenId);
        $this->assertSame($item->getKey(), $check->purchaseItemId);
        $this->assertSame($item->shop_product_id, $check->shopProductId);
        $this->assertSame($shop->getKey(), $check->shopId);
        $this->assertSame(0, TokenRedemption::query()->count(), 'Checking must not use the credential up.');

        $redeemed = $this->verifier()->redeem($buyer->getKey(), $secret, $item->shop_product_id);

        $this->assertTrue($redeemed->eligible);
        $this->assertNotNull($redeemed->redeemedAt);
        $this->assertSame($buyer->getKey(), TokenRedemption::query()->sole()->user_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'verification.credential_redeemed')->count());
    }

    public function test_a_credential_is_redeemed_only_once(): void
    {
        $buyer = User::factory()->create();
        [$secret] = $this->credentialFor($buyer);
        $this->verifier()->redeem($buyer->getKey(), $secret);

        $this->assertRuleViolated('verification.already_redeemed', fn () => $this->verifier()->redeem($buyer->getKey(), $secret));
        $this->assertSame(VerificationFailure::AlreadyRedeemed, $this->verifier()->checkEligibility($buyer->getKey(), $secret)->failure);
        $this->assertSame(1, TokenRedemption::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'verification.credential_redeemed')->count());
    }

    public function test_a_request_that_loses_the_race_is_rejected_as_already_redeemed(): void
    {
        $buyer = User::factory()->create();
        [$secret, , $token] = $this->credentialFor($buyer);

        // The competing request claims the token after this request has passed every check, so
        // only the primary key stands between them. One connection is shared in tests, which
        // means the rival row is rolled back together with this request's transaction.
        TokenRedemption::creating(function () use ($token): void {
            DB::table('token_redemptions')->insert([
                'verification_token_id' => $token->getKey(),
                'user_id' => $token->purchaseItem->purchase->buyer_user_id,
                'redeemed_at' => now(),
            ]);
        });

        $this->assertRuleViolated('verification.already_redeemed', fn () => $this->verifier()->redeem($buyer->getKey(), $secret));
        $this->assertSame(0, AuditLog::query()->where('action', 'verification.credential_redeemed')->count());
    }

    public function test_the_database_itself_refuses_a_second_redemption_of_a_token(): void
    {
        $token = VerificationToken::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        DB::table('token_redemptions')->insert(['verification_token_id' => $token->getKey(), 'user_id' => $first->getKey(), 'redeemed_at' => now()]);

        $this->expectException(QueryException::class);
        DB::table('token_redemptions')->insert(['verification_token_id' => $token->getKey(), 'user_id' => $second->getKey(), 'redeemed_at' => now()]);
    }

    public function test_a_credential_bought_by_a_named_customer_cannot_be_used_by_anyone_else(): void
    {
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        [$secret] = $this->credentialFor($buyer);

        $this->assertRuleViolated('verification.unknown_credential', fn () => $this->verifier()->redeem($stranger->getKey(), $secret));
        $this->assertSame(VerificationFailure::UnknownCredential, $this->verifier()->checkEligibility($stranger->getKey(), $secret)->failure);
        $this->assertSame(0, TokenRedemption::query()->count());

        $this->assertTrue($this->verifier()->redeem($buyer->getKey(), $secret)->eligible);
    }

    public function test_a_walk_in_credential_goes_to_the_first_valid_holder(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        [$secret] = $this->credentialFor(null);

        $this->assertTrue($this->verifier()->redeem($first->getKey(), $secret)->eligible);
        $this->assertRuleViolated('verification.already_redeemed', fn () => $this->verifier()->redeem($second->getKey(), $secret));
    }

    public function test_a_credential_only_verifies_the_listing_it_was_issued_for(): void
    {
        $buyer = User::factory()->create();
        [$secret, $item] = $this->credentialFor($buyer);
        $otherListing = ShopProduct::factory()->create();

        $this->assertSame(VerificationFailure::WrongScope, $this->verifier()->checkEligibility($buyer->getKey(), $secret, $otherListing->getKey())->failure);
        $this->assertRuleViolated('verification.wrong_scope', fn () => $this->verifier()->redeem($buyer->getKey(), $secret, $otherListing->getKey()));
        $this->assertSame(0, TokenRedemption::query()->count());
        $this->assertTrue($this->verifier()->checkEligibility($buyer->getKey(), $secret, $item->shop_product_id)->eligible);
    }

    public function test_expired_revoked_and_voided_credentials_are_rejected(): void
    {
        $buyer = User::factory()->create();
        [$expiredSecret, , $expired] = $this->credentialFor($buyer);
        [$revokedSecret, , $revoked, $revokedShop] = $this->credentialFor($buyer);
        [$voidedSecret, $voidedItem] = $this->credentialFor($buyer);

        $expired->forceFill(['issued_at' => now()->subDays(10), 'expires_at' => now()->subDay()])->save();
        app(VerificationCredentialService::class)->revokeCredential($this->staffOf($revokedShop), $revoked);
        $voidedItem->purchase->update(['status' => 'voided']);

        $this->assertRuleViolated('verification.expired', fn () => $this->verifier()->redeem($buyer->getKey(), $expiredSecret));
        $this->assertRuleViolated('verification.revoked', fn () => $this->verifier()->redeem($buyer->getKey(), $revokedSecret));
        $this->assertRuleViolated('verification.purchase_not_completed', fn () => $this->verifier()->redeem($buyer->getKey(), $voidedSecret));
        $this->assertSame(0, TokenRedemption::query()->count());
    }

    public function test_a_redeemed_credential_stays_redeemed_after_it_expires(): void
    {
        $buyer = User::factory()->create();
        [$secret, , $token] = $this->credentialFor($buyer);
        $this->verifier()->redeem($buyer->getKey(), $secret);

        $token->forceFill(['issued_at' => now()->subDays(100), 'expires_at' => now()->subDay()])->save();

        $this->assertSame(VerificationFailure::AlreadyRedeemed, $this->verifier()->checkEligibility($buyer->getKey(), $secret)->failure);
        $this->assertSame(1, TokenRedemption::query()->count());
    }

    public function test_unknown_malformed_and_foreign_secrets_are_indistinguishable(): void
    {
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        [$secret] = $this->credentialFor($buyer);
        $unknown = app(CredentialSecrets::class)->generate();

        $results = [
            $this->verifier()->checkEligibility($buyer->getKey(), $unknown),
            $this->verifier()->checkEligibility($buyer->getKey(), 'not a secret'),
            $this->verifier()->checkEligibility($buyer->getKey(), ''),
            $this->verifier()->checkEligibility($stranger->getKey(), $secret),
        ];

        foreach ($results as $result) {
            $this->assertFalse($result->eligible);
            $this->assertSame(VerificationFailure::UnknownCredential, $result->failure);
            $this->assertNull($result->purchaseItemId);
            $this->assertNull($result->verificationTokenId);
            $this->assertNull($result->shopId);
        }
    }

    public function test_results_and_failures_never_contain_the_secret(): void
    {
        $buyer = User::factory()->create();
        [$secret] = $this->credentialFor($buyer);

        $check = $this->verifier()->checkEligibility($buyer->getKey(), $secret);
        $this->assertStringNotContainsString($secret, json_encode($check));
        $this->assertStringNotContainsString(hash('sha256', $secret), json_encode($check));

        $this->verifier()->redeem($buyer->getKey(), $secret);

        try {
            $this->verifier()->redeem($buyer->getKey(), $secret);
            $this->fail('Expected the second redemption to be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertStringNotContainsString($secret, $violation->getMessage());
        }

        $stored = json_encode([DB::table('audit_logs')->get(), DB::table('token_redemptions')->get()]);
        $this->assertStringNotContainsString($secret, $stored);
    }

    public function test_only_active_customer_accounts_can_use_credentials(): void
    {
        $buyer = User::factory()->create();
        [$secret] = $this->credentialFor($buyer);
        $buyer->forceFill(['status' => 'suspended'])->save();

        $this->assertRuleViolated('verification.customer_not_allowed', fn () => $this->verifier()->redeem($buyer->getKey(), $secret));
        $this->assertRuleViolated('verification.customer_not_allowed', fn () => $this->verifier()->redeem('not-a-user-id', $secret));
        $this->assertSame(0, TokenRedemption::query()->count());
    }

    public function test_a_redeemed_credential_can_no_longer_be_reissued_or_revoked(): void
    {
        $buyer = User::factory()->create();
        [$secret, , $token, $shop] = $this->credentialFor($buyer);
        $staff = $this->staffOf($shop);
        $this->verifier()->redeem($buyer->getKey(), $secret);
        $digest = $token->fresh()->getRawOriginal('token_digest');

        $this->assertRuleViolated('verification.already_redeemed', fn () => app(VerificationCredentialService::class)->reissueCredential($staff, $token));
        $this->assertRuleViolated('verification.already_redeemed', fn () => app(VerificationCredentialService::class)->revokeCredential($staff, $token));

        $fresh = $token->fresh();
        $this->assertSame($digest, $fresh->getRawOriginal('token_digest'));
        $this->assertNull($fresh->revoked_at);
    }

    public function test_a_reissued_credential_works_with_its_new_secret_only(): void
    {
        $buyer = User::factory()->create();
        [$oldSecret, , $token, $shop] = $this->credentialFor($buyer);

        $newSecret = app(VerificationCredentialService::class)->reissueCredential($this->staffOf($shop), $token)->secret;

        $this->assertRuleViolated('verification.unknown_credential', fn () => $this->verifier()->redeem($buyer->getKey(), $oldSecret));
        $this->assertTrue($this->verifier()->redeem($buyer->getKey(), $newSecret)->eligible);
    }

    public function test_a_redemption_is_final(): void
    {
        $buyer = User::factory()->create();
        [$secret] = $this->credentialFor($buyer);
        $this->verifier()->redeem($buyer->getKey(), $secret);
        $redemption = TokenRedemption::query()->sole();

        foreach ([
            fn () => $redemption->update(['user_id' => User::factory()->create()->getKey()]),
            fn () => $redemption->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the change to be refused.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
    }
}
