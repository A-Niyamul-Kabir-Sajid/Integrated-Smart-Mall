<?php

namespace App\Modules\ShopOwnerOperations\Services;

/**
 * Splits a discount over the lines of an order in proportion to their totals, in whole
 * cents. Rounding remainders go to the last lines, so the parts always add up to the
 * discount and no line is discounted by more than its own total.
 */
final class DiscountAllocator
{
    /**
     * @param  list<int>  $lineTotalsCents
     * @return list<int>
     */
    public static function allocate(int $discountCents, array $lineTotalsCents): array
    {
        $total = array_sum($lineTotalsCents);

        if ($discountCents <= 0 || $total <= 0) {
            return array_fill(0, count($lineTotalsCents), 0);
        }

        $discountCents = min($discountCents, $total);
        $parts = array_map(fn (int $line): int => intdiv($discountCents * $line, $total), $lineTotalsCents);
        $left = $discountCents - array_sum($parts);

        for ($i = count($parts) - 1; $i >= 0 && $left > 0; $i--) {
            $room = $lineTotalsCents[$i] - $parts[$i];
            $extra = min($room, $left);
            $parts[$i] += $extra;
            $left -= $extra;
        }

        return $parts;
    }
}
