<?php

namespace App\Console\Commands;

use App\Mail\RenewalReminderToCustomer;
use App\Mail\RenewalsDue;
use App\Models\RenewalCycle;
use App\Models\RenewalReminder;
use App\Services\Accounts\RenewalReminders;
use App\Support\Clock;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The daily renewal reminder, at the time set in Configuration hub → Email
 * notifications. Our side gets one email per address listing everything due
 * for them; each customer gets one email listing their own items. Every cycle
 * reminded is logged against today, which is also what stops a second run
 * the same day from sending again.
 */
class SendRenewalReminders extends Command
{
    protected $signature = 'renewals:remind {--dry-run : List what would be sent, send nothing}';

    protected $description = 'Email renewal reminders to the team and, where switched on, to customers';

    public function handle(Settings $settings, RenewalReminders $reminders): int
    {
        if (! $settings->get('notify.renewal.enabled')) {
            $this->info('Renewal reminders are switched off.');

            return self::SUCCESS;
        }

        $today = Clock::today();
        $cycles = $reminders->inWindow($today);

        // address => cycles; one email per address.
        $team = [];
        // customer email => cycles
        $customers = [];

        foreach ($cycles as $cycle) {
            if ($reminders->teamDue($cycle, $today)) {
                foreach ($reminders->teamRecipients($cycle->renewal) as $email) {
                    $team[$email][] = $cycle;
                }
            }

            if ($reminders->customerDue($cycle, $today)) {
                $customers[$reminders->customerEmail($cycle->renewal)][] = $cycle;
            }
        }

        if ($this->option('dry-run')) {
            foreach ($team as $email => $list) {
                $this->line("team  {$email}: ".collect($list)->map(fn ($c) => $c->renewal->title)->join(', '));
            }
            foreach ($customers as $email => $list) {
                $this->line("customer  {$email}: ".collect($list)->map(fn ($c) => $c->renewal->title)->join(', '));
            }

            return self::SUCCESS;
        }

        $sentTeam = $this->sendAll($team, RenewalReminder::TEAM, $today, fn (Collection $rows, string $email, Collection $list) => new RenewalsDue($rows, $email), $reminders);
        $sentCustomers = $this->sendAll($customers, RenewalReminder::CUSTOMER, $today, fn (Collection $rows, string $email, Collection $list) => new RenewalReminderToCustomer($rows, $list->first()->renewal->customer->name), $reminders);

        $this->info("{$cycles->count()} renewals in their reminder window; {$sentTeam} team and {$sentCustomers} customer emails sent.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, list<RenewalCycle>>  $groups
     */
    private function sendAll(array $groups, string $audience, $today, callable $mail, RenewalReminders $reminders): int
    {
        $sent = 0;
        // cycle id => addresses actually emailed
        $reached = [];

        foreach ($groups as $email => $list) {
            $list = collect($list)->sortBy(fn (RenewalCycle $c) => $c->due_date)->values();

            try {
                Mail::to($email)->send($mail($list->map(fn (RenewalCycle $c) => $reminders->row($c))->values(), $email, $list));
                $sent++;

                foreach ($list as $cycle) {
                    $reached[$cycle->id][] = $email;
                }
            } catch (Throwable $e) {
                // One bad address must not stop everyone else's reminder.
                report($e);
            }
        }

        $cycles = collect($groups)->flatten()->keyBy('id');

        foreach ($reached as $cycleId => $emails) {
            RenewalReminder::query()->insertOrIgnore([
                'renewal_cycle_id' => $cycleId,
                'audience' => $audience,
                'sent_on' => $today->toDateString(),
                'recipients' => json_encode(array_values(array_unique($emails))),
                'days_left' => $cycles[$cycleId]->daysLeft(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $sent;
    }
}
