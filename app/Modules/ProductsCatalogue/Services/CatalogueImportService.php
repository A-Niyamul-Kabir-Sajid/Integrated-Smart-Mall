<?php

namespace App\Modules\ProductsCatalogue\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ProductCategory;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;

/**
 * Bulk catalogue import from CSV. preview() validates every row and plans what
 * each row will do without writing anything; import() applies a clean plan in
 * one transaction, so a file is imported completely or not at all.
 */
class CatalogueImportService
{
    public const MAX_ROWS = 500;

    /**
     * Columns of the CSV header, in order.
     *
     * @var list<string>
     */
    public const COLUMNS = ['name', 'brand', 'category', 'option', 'option_code', 'sku', 'price', 'availability'];

    public function __construct(private CatalogueService $catalogue) {}

    /**
     * Validates and plans every row. Row numbers count the header as row 1, as spreadsheets do.
     *
     * @return array{rows: list<array<string, mixed>>, errors: array<int, list<string>>}
     */
    public function preview(Shop $shop, string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $header = array_map(fn ($cell): string => Str::lower(trim((string) $cell, " \t\n\r\0\x0B\xEF\xBB\xBF")), (array) $file->current());

        if ($header !== self::COLUMNS) {
            return ['rows' => [], 'errors' => [1 => [__('The header must be: :columns.', ['columns' => implode(',', self::COLUMNS)])]]];
        }

        $categories = ProductCategory::query()->pluck('product_category_id', 'slug');
        $existingSkus = ShopProduct::query()->where('shop_id', $shop->getKey())->pluck('seller_sku')->flip();
        $rows = [];
        $errors = [];
        $seenSkus = [];
        $file->next();

        while (! $file->eof() && $file->valid()) {
            $line = $file->key() + 1;
            $cells = (array) $file->current();
            $file->next();

            if (count($rows) + count($errors) >= self::MAX_ROWS) {
                $errors[$line] = [__('A file may contain at most :max products.', ['max' => self::MAX_ROWS])];
                break;
            }

            if (count($cells) !== count(self::COLUMNS)) {
                $errors[$line] = [__('Expected :count columns.', ['count' => count(self::COLUMNS)])];

                continue;
            }

            $row = array_combine(self::COLUMNS, array_map(fn ($cell): string => trim((string) $cell), $cells));
            $rowErrors = $this->validateRow($row, $categories->all(), $existingSkus->all(), $seenSkus);

            if ($rowErrors === []) {
                [$action, $rowErrors] = $this->plan($shop, $row);
                $row['action'] = $action;
                $row['category_id'] = $categories[$row['category']];
            }

            $seenSkus[$row['sku']] = true;

            if ($rowErrors !== []) {
                $errors[$line] = $rowErrors;

                continue;
            }

            $rows[] = ['line' => $line, ...$row];
        }

        if ($rows === [] && $errors === []) {
            $errors[1] = [__('The file has no products.')];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Applies a clean preview. Any failure rolls back every row.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function import(Shop $shop, array $rows): int
    {
        return DB::transaction(function () use ($shop, $rows): int {
            foreach ($rows as $row) {
                $listing = ['seller_sku' => $row['sku'], 'price_amount' => $row['price'], 'availability_status' => $row['availability']];
                $variant = ['variant_code' => $row['option_code'], 'label' => $row['option']];

                try {
                    $product = $this->findProduct($row);
                    $existingVariant = $product?->variants()->where('variant_code', $this->variantCode($row))->first();

                    match (true) {
                        $existingVariant !== null => $this->catalogue->listExistingVariant($shop, $existingVariant->load('product'), $listing),
                        $product !== null => $this->catalogue->addVariantListing($shop, $product, $variant, $listing),
                        default => $this->catalogue->createProductListing($shop, [
                            'product_category_id' => $row['category_id'],
                            'name' => $row['name'],
                            'brand' => $row['brand'],
                        ], $variant, $listing),
                    };
                } catch (BusinessRuleViolation $violation) {
                    throw new BusinessRuleViolation($violation->rule, __('Row :line: :message', ['line' => $row['line'], 'message' => $violation->getMessage()]));
                }
            }

            return count($rows);
        });
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $categories
     * @param  array<string, int>  $existingSkus
     * @param  array<string, true>  $seenSkus
     * @return list<string>
     */
    private function validateRow(array $row, array $categories, array $existingSkus, array $seenSkus): array
    {
        $errors = [];

        if ($row['name'] === '' || mb_strlen($row['name']) > 160) {
            $errors[] = __('Name is required (at most 160 characters).');
        }

        if (! isset($categories[$row['category']])) {
            $errors[] = __('Unknown category ":category".', ['category' => $row['category']]);
        }

        if ($row['option'] === '') {
            $errors[] = __('Option is required, e.g. "500 ml bottle" or "Standard".');
        }

        if (preg_match('/^[A-Za-z0-9._-]{1,80}$/', $row['sku']) !== 1) {
            $errors[] = __('SKU must use letters, digits, dots, dashes or underscores.');
        } elseif (isset($existingSkus[$row['sku']])) {
            $errors[] = __('Your shop already uses the SKU :sku.', ['sku' => $row['sku']]);
        } elseif (isset($seenSkus[$row['sku']])) {
            $errors[] = __('The SKU :sku appears more than once in this file.', ['sku' => $row['sku']]);
        }

        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $row['price']) !== 1) {
            $errors[] = __('Price must be a number of zero or more with at most two decimals.');
        }

        if (! in_array($row['availability'], ['available', 'unavailable', 'unknown'], true)) {
            $errors[] = __('Availability must be available, unavailable or unknown.');
        }

        return $errors;
    }

    /**
     * Decides whether a valid row lists an existing option, adds an option or creates a product.
     *
     * @param  array<string, string>  $row
     * @return array{0: string, 1: list<string>}
     */
    private function plan(Shop $shop, array $row): array
    {
        $product = $this->findProduct($row + ['category_id' => null]);

        if ($product === null) {
            return ['new product', []];
        }

        $variant = $product->variants()->where('variant_code', $this->variantCode($row))->first();

        if ($variant !== null) {
            return ShopProduct::query()->where('shop_id', $shop->getKey())->where('variant_id', $variant->getKey())->exists()
                ? ['', [__('Your shop already lists this product option.')]]
                : ['list existing', []];
        }

        return $this->catalogue->canEditSharedProduct($shop, $product)
            ? ['new option', []]
            : ['', [__('Other shops sell this product; ask the mall office to add the option ":option".', ['option' => $row['option']])]];
    }

    /**
     * An existing active product with the same name, brand and category (letter case ignored).
     *
     * @param  array<string, mixed>  $row
     */
    private function findProduct(array $row): ?Product
    {
        return Product::query()
            ->active()
            ->whereRaw('lower(name) = ?', [Str::lower($row['name'])])
            ->when(
                $row['brand'] === '',
                fn ($query) => $query->whereNull('brand'),
                fn ($query) => $query->whereRaw('lower(brand) = ?', [Str::lower($row['brand'])]),
            )
            ->whereHas('category', fn ($category) => $category->where('slug', $row['category']))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function variantCode(array $row): string
    {
        return Str::slug((string) $row['option_code']) ?: 'default';
    }
}
