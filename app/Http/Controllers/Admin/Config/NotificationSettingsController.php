<?php

namespace App\Http\Controllers\Admin\Config;

use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration hub → Email notifications: which task and bug events send
 * email, and when the daily overdue email goes out.
 */
class NotificationSettingsController extends Controller
{
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
                'overdue_owners' => (bool) $s->get('notify.overdue.owners'),
                'overdue_time' => (string) $s->get('notify.overdue.time'),
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
            'overdue_owners' => ['required', 'boolean'],
            'overdue_time' => ['required', 'date_format:H:i'],
        ]);

        $this->settings->set([
            'notify.task.assigned' => (bool) $data['task_assigned'],
            'notify.task.urgent' => (bool) $data['task_urgent'],
            'notify.task.status' => array_values(array_unique($data['task_status'])),
            'notify.bug.assigned' => (bool) $data['bug_assigned'],
            'notify.bug.status' => array_values(array_unique($data['bug_status'])),
            'notify.overdue.enabled' => (bool) $data['overdue_enabled'],
            'notify.overdue.owners' => (bool) $data['overdue_owners'],
            'notify.overdue.time' => $data['overdue_time'],
        ]);

        return back()->with('success', 'Email notifications saved.');
    }
}
