<?php

namespace Database\Factories;

use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A bare document record without text. Tests that need retrievable text capture it through
 * KnowledgeService, which writes the revision and its chunks.
 *
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mall_id' => Mall::factory(),
            'shop_id' => null,
            'shop_product_id' => null,
            'source_kind' => 'faq',
            'title' => Str::title(fake()->unique()->words(3, true)),
            'source_locator' => 'manual:'.Str::uuid(),
            'access_scope' => 'public',
            'status' => 'active',
            'current_revision_id' => null,
        ];
    }

    /**
     * Readable by the mall's administrators only.
     */
    public function restricted(): static
    {
        return $this->state(fn (array $attributes) => ['source_kind' => 'admin_report', 'access_scope' => 'mall_admin']);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'withdrawn']);
    }
}
