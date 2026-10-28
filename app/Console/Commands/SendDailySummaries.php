<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Mail\DailySummary;
use App\Models\Project;
use App\Models\User;
use App\Services\DailySummary\ProjectSummary;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The daily summary, scheduled at the time set in Configuration hub → Email
 * notifications. Each address gets one email, whichever lists it is on:
 *
 * - a leadership address gets every active project;
 * - a project's owner and the people added on its Members tab get that project.
 *
 * "Changed since the last summary" is measured per address, from the last
 * summary that address was actually sent.
 */
class SendDailySummaries extends Command
{
    protected $signature = 'summaries:daily';

    protected $description = 'Email the daily project summary to leadership, project owners and project recipients';

    public function handle(Settings $settings, ProjectSummary $summary): int
    {
        $leaders = $settings->get('notify.summary.leaders') ? (array) $settings->get('notify.summary.emails') : [];
        $perProject = (bool) $settings->get('notify.summary.projects');

        if ($leaders === [] && ! $perProject) {
            $this->info('Daily summaries are switched off.');

            return self::SUCCESS;
        }

        // Taken before reading anything, so a change made while this runs falls
        // in the next summary's window rather than in neither.
        $startedAt = now();

        // Only live work, as with the overdue email.
        $projects = Project::query()->where('status', ProjectStatus::Active)
            ->with(['owner:id,name,email,is_active', 'summaryRecipients.user:id,email,is_active'])
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        /** @var array<string, array{projects: array<int, true>, leadership: bool}> $plan */
        $plan = [];

        foreach ($leaders as $email) {
            $plan[$email] = ['projects' => array_fill_keys($projects->keys()->all(), true), 'leadership' => true];
        }

        if ($perProject) {
            foreach ($projects as $project) {
                $addresses = $project->summaryRecipients->toBase()->map->address()
                    ->push($project->owner?->is_active ? strtolower($project->owner->email) : null)
                    ->filter()
                    ->unique();

                foreach ($addresses as $email) {
                    $plan[$email] ??= ['projects' => [], 'leadership' => false];
                    $plan[$email]['projects'][$project->id] = true;
                }
            }
        }

        $lastSent = DB::table('summary_deliveries')->whereIn('email', array_keys($plan))->pluck('last_sent_at', 'email');
        $names = User::whereIn(DB::raw('lower(email)'), array_keys($plan))->get(['name', 'email'])
            ->mapWithKeys(fn (User $u) => [strtolower($u->email) => $u->name]);
        $built = [];
        $sent = 0;

        foreach ($plan as $email => $entry) {
            if ($entry['projects'] === []) {
                continue;
            }

            $since = isset($lastSent[$email]) ? Carbon::parse($lastSent[$email]) : $startedAt->copy()->subDay();
            $sections = collect(array_keys($entry['projects']))
                ->map(fn (int $id) => $built[$id.'@'.$since->timestamp] ??= $summary->build($projects->get($id), $since))
                ->all();

            try {
                Mail::to($email)->send(new DailySummary($sections, $since, $entry['leadership'], $names->get($email)));
                $this->delivered($email, $startedAt);
                $sent++;
            } catch (Throwable $e) {
                // One bad address must not stop everyone else's email, and its
                // window stays open so tomorrow's summary still has the changes.
                report($e);
            }
        }

        $this->info("{$projects->count()} active projects; {$sent} summaries sent.");

        return self::SUCCESS;
    }

    private function delivered(string $email, Carbon $at): void
    {
        DB::table('summary_deliveries')->updateOrInsert(['email' => $email], ['last_sent_at' => $at]);
    }
}
