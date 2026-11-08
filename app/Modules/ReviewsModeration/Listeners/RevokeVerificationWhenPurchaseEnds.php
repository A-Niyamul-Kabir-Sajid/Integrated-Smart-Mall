<?php

namespace App\Modules\ReviewsModeration\Listeners;

use App\Modules\PurchaseVerification\Contracts\PurchaseVerifier;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Events\PurchaseStatusChanged;
use App\Modules\ReviewsModeration\Models\ReviewVerification;
use App\Modules\SharedPlatform\Services\AuditLogger;

/**
 * When a sale is voided or refunded, the reviews it verified keep their text and rating but
 * lose the verified badge. The binding row stays as history and still blocks reuse of the credential.
 */
class RevokeVerificationWhenPurchaseEnds
{
    public function __construct(
        private PurchaseVerifier $verifier,
        private AuditLogger $audit,
    ) {}

    public function handle(PurchaseStatusChanged $event): void
    {
        if (! in_array($event->to, [PurchaseStatus::Voided, PurchaseStatus::Refunded], true)) {
            return;
        }

        $tokenIds = $this->verifier->redeemedTokenIdsForPurchase($event->purchaseId);

        if ($tokenIds === []) {
            return;
        }

        ReviewVerification::query()
            ->whereIn('verification_token_id', $tokenIds)
            ->whereNull('revoked_at')
            ->with('review.shop')
            ->get()
            ->each(function (ReviewVerification $verification) use ($event): void {
                $verification->update(['revoked_at' => now(), 'revocation_reason' => "Purchase {$event->to->value}"]);

                $review = $verification->review;
                $this->audit->record('review.verification_revoked', $review, ['reason' => $event->to->value], $review->shop->mall_id, $review->shop_id);
            });
    }
}
