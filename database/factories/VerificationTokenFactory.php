<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use App\Modules\PurchaseVerification\Models\VerificationToken;
use App\Modules\PurchaseVerification\Services\CredentialSecrets;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationToken>
 */
class VerificationTokenFactory extends Factory
{
    protected $model = VerificationToken::class;

    /**
     * Define the model's default state: a credential valid for 90 days. The raw secret is
     * not kept; use withSecret() when a test needs to present it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_item_id' => PurchaseItem::factory(),
            'token_digest' => app(CredentialSecrets::class)->digest(app(CredentialSecrets::class)->generate()),
            'issued_by_user_id' => User::factory(),
            'issued_at' => now()->subMinute(),
            'expires_at' => now()->addDays(90),
            'revoked_at' => null,
        ];
    }

    /**
     * Stores the digest of a known secret so a test can present that secret.
     */
    public function withSecret(string $secret): static
    {
        return $this->state(fn (array $attributes) => ['token_digest' => app(CredentialSecrets::class)->digest($secret)]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['issued_at' => now()->subDays(100), 'expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now()]);
    }
}
