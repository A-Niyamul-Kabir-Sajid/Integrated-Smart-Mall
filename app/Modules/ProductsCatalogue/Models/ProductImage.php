<?php

namespace App\Modules\ProductsCatalogue\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'product_images', key: 'product_image_id', timestamps: false)]
#[Fillable(['product_id', 'object_key', 'alt_text', 'sort_order'])]
class ProductImage extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
