<?php

namespace App\Http\Controllers\Admin\Config;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Http\Controllers\Controller;
use App\Mail\DailySummary;
use App\Models\Project;
use App\Services\DailySummary\ProjectSummary;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration hub → Email notifications: which task and bug events send
 * email, when the daily overdue email goes out, and who gets the daily summary.
 */
class NotificationSettingsController extends Controller
{
    private const MAX_LEADERS = 20;

    public function __construct(private readonly Settings $settings) {}

    public function edit(): Response
    {
        $s = $this->settings;

        return Inertia::render('admin/config/notifications', [
            'settings' => [
                'task_assigned' => (bool) $s->get('notify.task.assigned'),
                'task_urgent' => (bool) $s->get('notify.task.urgent'),
                'task_status' => array_values((array) $s->get('notify.task.status')),
                'bug_assigned' => (bool) $s->get('notify.bug.assigned'),
                'bug_status' => array_values((array) $s->get('notify.bug.status')),
                'overdue_enabled' => (bool) $s->get('notify.overdue.enabled'),
                'overdue_time' => (string) $s->get('notify.overdue.time'),
                'summary_leaders' => (bool) $s->get('notify.summary.leaders'),
                'summary_emails' => array_values((array) $s->get('notify.summary.emails')),
                'summary_projects' => (bool) $s->get('notify.summary.projects'),
                'summary_time' => (string) $s->get('notify.summary.time'),
                'renewal_enabled' => (bool) $s->get('notify.renewal.enabled'),
                'renewal_time' => (string) $s->get('notify.renewal.time'),
                'renewal_emails' => array_values((array) $s->get('notify.renewal.emails')),
                'renewal_customer' => (bool) $s->get('notify.renewal.customer'),
                'renewal_customer_every' => (int) $s->get('notify.renewal.customer_every'),
                'renewal_start' => (bool) $s->get('notify.renewal.start'),
            ],
            'taskStatuses' => TaskStatus::options(),
            'bugStatuses' => TestPointStatus::options(),
            'timezone' => (string) $s->get('display.timezone'),
            'mailIsLocal' => in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'task_assigned' => ['required', 'boolean'],
            'task_urgent' => ['required', 'boolean'],
            'task_status' => ['present', 'array'],
            'task_status.*' => [Rule::enum(TaskStatus::class)],
            'bug_assigned' => ['required', 'boolean'],
            'bug_status' => ['present', 'array'],
            'bug_status.*' => [Rule::enum(TestPointStatus::class)],
            'overdue_enabled' => ['required', 'boolean'],
            'overdue_time' => ['required', 'date_format:H:i'],
            'summary_leaders' => ['required', 'boolean'],
            'summary_emails' => ['present', 'array', 'max:'.self::MAX_LEADERS],
            'summary_emails.*' => ['required', 'string', 'email:rfc', 'max:255'],
            'summary_projects' => ['required', 'boolean'],
            'summary_time' => ['required', 'date_format:H:i'],
            'renewal_enabled' => ['sometimes', 'required', 'boolean'],
            'renewal_time' => ['required_with:renewal_enabled', 'date_format:H:i'],
            'renewal_emails' => ['nullable', 'array', 'max:'.self::MAX_LEADERS],
            'renewal_emails.*' => ['required', 'string', 'email:rfc', 'max:255'],
            'renewal_customer' => ['required_with:renewal_enabled', 'boolean'],
            'renewal_customer_every' => ['required_with:renewal_enabled', 'integer', 'min:1', 'max:30'],
            'renewal_start' => ['required_with:renewal_enabled', 'boolean'],
        ], [
            'renewal_emails.*.email' => 'Each renewal reminder address must be a valid email address.',
            'summary_emails.*.email' => 'Each leadership address must be a valid email address.',
            'summary_emails.max' => 'Up to '.self::MAX_LEADERS.' leadership addresses.',
        ]);

        $this->settings->set([
            'notify.task.assigned' => (bool) $data['task_assigned'],
            'notify.task.urgent' => (bool) $data['task_urgent'],
            'notify.task.status' => array_values(array_unique($data['task_status'])),
            'notify.bug.assigned' => (bool) $data['bug_assigned'],
            'notify.bug.status' => array_values(array_unique($data['bug_status'])),
            'notify.overdue.enabled' => (bool) $data['overdue_enabled'],
            'notify.overdue.time' => $data['overdue_time'],
            'notify.summary.leaders' => (bool) $data['summary_leaders'],
            // Lower-cased so one person typed twice in different case is one address.
            'notify.summary.emails' => array_values(array_unique(array_map(fn (string $e) => strtolower(trim($e)), $data['summary_emails']))),
            'notify.summary.projects' => (bool) $data['summary_projects'],
            'notify.summary.time' => $data['summary_time'],
        ]);

        // The renewal card is sent with the rest of the page; older clients may not send it.
        if (array_key_exists('renewal_enabled', $data)) {
            $this->settings->set([
                'notify.renewal.enabled' => (bool) $data['renewal_enabled'],
                'notify.renewal.time' => $data['renewal_time'],
                'notify.renewal.emails' => array_values(array_unique(array_map(fn (string $e) => strtolower(trim($e)), $data['renewal_emails'] ?? []))),
                'notify.renewal.customer' => (bool) $data['renewal_customer'],
                'notify.renewal.customer_every' => (int) $data['renewal_customer_every'],
                'notify.renewal.start' => (bool) $data['renewal_start'],
            ]);
        }

        return back()->with('success', 'Email notifications saved.');
    }

    /**
     * The leadership summary as it would look now: every active project, with
     * the last 24 hours as "since the last summary". Nothing is sent.
     */
    public function preview(Request $request, ProjectSummary $summary): DailySummary
    {
        $since = now()->subDay();
        $projects = Project::query()->where('status', ProjectStatus::Active)->orderBy('name')->get()
            ->map(fn (Project $p) => $summary->build($p, $since))
            ->all();

        return new DailySummary($projects, $since, true, $request->user()->name);
    }
}
