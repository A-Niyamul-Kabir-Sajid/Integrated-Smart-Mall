<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ProductVariant;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CatalogueImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "name,brand,category,option,option_code,sku,price,availability\n";

    private Shop $shop;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        ProductCategory::factory()->create(['slug' => 'drinks']);
        $this->shop = Shop::factory()->create();
        $this->staff = $this->staffOf($this->shop);
    }

    private function staffOf(Shop $shop): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantShopRole($user, $shop, RoleCode::ShopStaff);

        return $user;
    }

    private function preview(string $csv, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->staff)->post(route('workspace.catalogue.import.preview', $this->shop), [
            'file' => UploadedFile::fake()->createWithContent('catalogue.csv', $csv),
        ]);
    }

    public function test_a_clean_file_is_previewed_then_imported_on_confirmation(): void
    {
        $response = $this->preview(self::HEADER
            ."Mineral Water,AquaPure,drinks,500 ml bottle,500ml,MW-500,20,available\n"
            ."Mineral Water,AquaPure,drinks,1.5 litre bottle,1500ml,MW-1500,35.50,unavailable\n");

        $response->assertOk()->assertSee('2 products are ready to import.')->assertSee('new product');
        $this->assertSame(0, ShopProduct::query()->count(), 'A preview must not write.');

        $this->actingAs($this->staff)
            ->post(route('workspace.catalogue.import.store', $this->shop), ['token' => $response->viewData('token')])
            ->assertRedirect(route('workspace.catalogue.index', $this->shop))
            ->assertSessionHas('status', '2 products were imported.');

        $product = Product::query()->sole();
        $this->assertSame(2, $product->variants()->count());
        $this->assertEqualsCanonicalizing(['20.00', '35.50'], ShopProduct::query()->pluck('price_amount')->all());
    }

    public function test_every_invalid_row_is_reported_and_nothing_is_written(): void
    {
        ShopProduct::factory()->for($this->shop)->create(['seller_sku' => 'TAKEN']);

        $response = $this->preview(self::HEADER
            ."Good Water,AquaPure,drinks,500 ml,500ml,OK-1,20,available\n"
            .",AquaPure,toys,,x,bad sku,-3,soon\n"
            ."Cola,Fizzo,drinks,Can,can,TAKEN,40,available\n"
            ."Juice,,drinks,Cup,cup,OK-1,10,available\n"
            ."too,few,columns\n");

        $response->assertOk()->assertSee('4 rows need fixing. Nothing was imported.');
        $rowErrors = $response->viewData('rowErrors');
        $this->assertSame([3, 4, 5, 6], array_keys($rowErrors));
        $this->assertCount(6, $rowErrors[3]);
        $this->assertSame(['Your shop already uses the SKU TAKEN.'], $rowErrors[4]);
        $this->assertSame(['The SKU OK-1 appears more than once in this file.'], $rowErrors[5]);
        $this->assertNull($response->viewData('token'));
        $this->assertSame(1, ShopProduct::query()->count());
    }

    public function test_rows_reuse_existing_shared_products_and_respect_other_shops(): void
    {
        $water = Product::factory()->create(['name' => 'Mineral Water', 'brand' => 'AquaPure', 'product_category_id' => ProductCategory::query()->where('slug', 'drinks')->value('product_category_id')]);
        $bottle = ProductVariant::factory()->for($water)->create(['variant_code' => '500ml']);
        ShopProduct::factory()->for($bottle, 'variant')->create();

        $response = $this->preview(self::HEADER
            ."mineral water,aquapure,drinks,500 ml bottle,500ml,MW-1,22,available\n"
            ."Mineral Water,AquaPure,drinks,2 litre bottle,2l,MW-2,40,available\n");

        $this->assertSame(['list existing'], array_column($response->viewData('rows'), 'action'));
        $this->assertSame([3], array_keys($response->viewData('rowErrors')));
        $this->assertStringContainsString('ask the mall office', $response->viewData('rowErrors')[3][0]);
    }

    public function test_a_wrong_header_is_rejected(): void
    {
        $this->preview("product,price\nWater,20\n")->assertSee('The header must be: '.rtrim(self::HEADER));
    }

    public function test_a_preview_token_works_once_and_only_for_its_user_and_shop(): void
    {
        $token = $this->preview(self::HEADER."Water,AquaPure,drinks,Bottle,b,W-1,20,available\n")->viewData('token');
        $otherStaff = $this->staffOf($this->shop);

        $this->actingAs($otherStaff)
            ->post(route('workspace.catalogue.import.store', $this->shop), ['token' => $token])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, ShopProduct::query()->count());

        $token = $this->preview(self::HEADER."Water,AquaPure,drinks,Bottle,b,W-1,20,available\n")->viewData('token');
        $this->actingAs($this->staff)->post(route('workspace.catalogue.import.store', $this->shop), ['token' => $token])->assertRedirect(route('workspace.catalogue.index', $this->shop));
        $this->actingAs($this->staff)->post(route('workspace.catalogue.import.store', $this->shop), ['token' => $token])->assertSessionHasErrors('file');
        $this->assertSame(1, ShopProduct::query()->count());
    }

    public function test_staff_of_another_shop_cannot_import(): void
    {
        $this->preview(self::HEADER."Water,AquaPure,drinks,Bottle,b,W-1,20,available\n", $this->staffOf(Shop::factory()->create()))
            ->assertForbidden();
    }
}
