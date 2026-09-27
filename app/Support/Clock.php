<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Today's date as the organisation sees it. The app stores timestamps in UTC
 * (see Settings: display.timezone never changes config('app.timezone')), so
 * `today()` alone is the UTC date — a day behind in India until 05:30. Every
 * "is it overdue / due this week / today's date" question asks this instead.
 */
final class Clock
{
    /** The local calendar date, as a date (midnight) comparable with date columns. */
    public static function today(): Carbon
    {
        $timezone = rescue(fn () => (string) app(Settings::class)->get('display.timezone', 'UTC'), 'UTC', false) ?: 'UTC';

        return Carbon::parse(now($timezone)->toDateString());
    }
}
