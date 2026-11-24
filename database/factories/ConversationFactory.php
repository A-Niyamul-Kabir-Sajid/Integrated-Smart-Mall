<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Models\Conversation;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * A guest's public conversation that started now and is kept for a day.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mall_id' => Mall::factory(),
            'user_id' => null,
            'access_scope' => 'public',
            'created_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->getKey(), 'expires_at' => now()->addDays(30)]);
    }

    /**
     * A mall administrator's conversation with the administrators' assistant.
     */
    public function administrative(User $administrator): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $administrator->getKey(), 'access_scope' => 'mall_admin', 'expires_at' => now()->addDays(30)]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['created_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
    }
}
