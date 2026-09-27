<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

use function Illuminate\Support\defer;

/**
 * How work emails go out. The change they describe is already saved, so:
 *
 * - they are sent only once it is committed;
 * - in a web request they are sent after the response has gone, so nobody's
 *   board drag waits on the mail server;
 * - a failure is logged, never shown — a mail outage must not turn a saved
 *   change into an error page.
 *
 * Console commands and tests run the send straight away.
 */
final class SendsMailSafely
{
    public static function afterCommit(callable $send): void
    {
        DB::afterCommit(function () use ($send) {
            $guarded = static function () use ($send) {
                try {
                    $send();
                } catch (Throwable $e) {
                    report($e);
                }
            };

            app()->runningInConsole() ? $guarded() : defer($guarded);
        });
    }
}
