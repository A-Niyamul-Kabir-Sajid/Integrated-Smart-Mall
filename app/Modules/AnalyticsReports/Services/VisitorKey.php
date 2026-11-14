<?php

namespace App\Modules\AnalyticsReports\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * An anonymous key for counting a visitor once per day. It is a keyed hash of the day, the address
 * and the browser, so it changes every day (a person cannot be followed across days) and cannot be
 * turned back into an address without the application key.
 */
class VisitorKey
{
    public function for(Request $request, CarbonInterface $day): string
    {
        return hash_hmac('sha256', $day->format('Y-m-d').'|'.$request->ip().'|'.$request->userAgent(), (string) config('app.key'));
    }
}
