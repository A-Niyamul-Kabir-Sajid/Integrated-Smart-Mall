<?php

namespace App\Modules\ChatbotRag\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MESSAGE_RECOMMENDATION: a product listing an answer recommended, in display order, with the
 * reason. Only the listing id is kept: price and availability are read from the catalogue every time
 * the conversation is shown. Composite primary key (message_id, shop_product_id), so rows are read
 * through the message relationship and written with query-builder inserts.
 */
#[Table(name: 'message_recommendations', incrementing: false, timestamps: false)]
#[Fillable(['message_id', 'shop_product_id', 'rank_no', 'reason'])]
class MessageRecommendation extends Model
{
    protected $primaryKey = null;

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    protected function casts(): array
    {
        return [
            'rank_no' => 'integer',
        ];
    }
}
