<?php

namespace App\Modules\MapsNavigation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MapsNavigation\Contracts\MapServiceGateway;
use App\Modules\MapsNavigation\Contracts\MapServiceUnavailable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * The visitor's indoor map of a mall (ISM-64): the map service's embedded
 * map in an iframe, opened from a signed link this server asks for with the
 * API key. A scanned QR code lands on qr(), which starts the map where the
 * code hangs.
 */
class MallMapController extends Controller
{
    private const PLACE = '/^(checkpoint|node|unit|facility|facility-type):[^\x00-\x1F]{1,190}$/u';

    public function __construct(private MapServiceGateway $maps) {}

    public function show(Request $request, Mall $publicMall): Response
    {
        $from = $this->place($request->query('from'));
        $to = $this->place($request->query('to'));
        $written = array_filter([$request->query('from'), $request->query('to')], fn ($value): bool => $value !== null && $value !== '');
        $notice = count($written) > count(array_filter([$from, $to])) ? __('That place is not on the map. Choose it from the list.') : null;

        return $this->page($publicMall, $from, $to, $request->boolean('step_free'), $notice);
    }

    public function qr(Mall $publicMall, string $slug): Response
    {
        try {
            $checkpoint = $this->maps->checkpoint($slug);
        } catch (MapServiceUnavailable $error) {
            return match ($error->reason) {
                'retired', 'not_on_map' => $this->page($publicMall, null, null, false, __('This QR code is no longer in use. Choose where you are on the map, or scan another code.')),
                'unknown' => abort(404),
                default => $this->unavailable($publicMall, $error),
            };
        }

        abort_unless(($checkpoint['mall_ref'] ?? null) === $publicMall->slug, 404);

        return $this->page($publicMall, (string) $checkpoint['start'], null, false, null, $checkpoint['label'] ?? null);
    }

    private function page(Mall $mall, ?string $from, ?string $to, bool $stepFree, ?string $notice = null, ?string $startLabel = null): Response
    {
        try {
            $link = $this->maps->embedLink($mall->slug, [
                'from' => $from,
                'to' => $to === null ? [] : [$to],
                'step_free' => $stepFree,
                'lang' => app()->getLocale() === 'bn' ? 'bn' : 'en',
            ]);
        } catch (MapServiceUnavailable $error) {
            if ($error->reason === 'unknown_place') {
                return $this->page($mall, null, null, $stepFree, __('That place is not on the map. Choose it from the list.'));
            }

            return $this->unavailable($mall, $error);
        }

        if (! is_string($link['url'] ?? null) || ! str_starts_with($link['url'], 'http')) {
            return $this->unavailable($mall, new MapServiceUnavailable('The map service answered without an embed link.'));
        }

        return response()->view('customer.maps.show', [
            'mall' => $mall,
            'embedUrl' => $link['url'],
            'mapOrigin' => $this->origin($link['url']),
            'notice' => $notice,
            'startLabel' => $startLabel,
        ]);
    }

    private function unavailable(Mall $mall, MapServiceUnavailable $error): Response
    {
        $noMap = $error->reason === 'no_map';
        if (! $noMap) {
            Log::warning('The map service could not open the mall map.', ['mall' => $mall->slug, 'status' => $error->status, 'reason' => $error->reason, 'message' => $error->getMessage()]);
        }

        return response()->view('customer.maps.show', [
            'mall' => $mall,
            'embedUrl' => null,
            'mapOrigin' => null,
            'notice' => $noMap ? __('This mall has no indoor map yet.') : __('The map is not available right now. Please try again in a few minutes.'),
            'startLabel' => null,
        ], $noMap ? 404 : 503);
    }

    /**
     * A place written kind:value from the address, or null when it is missing or malformed.
     */
    private function place(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 200 && preg_match(self::PLACE, $value) === 1 ? $value : null;
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
