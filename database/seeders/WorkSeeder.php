<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo projects and tasks, so the board and dashboard have something to show
 * on a fresh install.
 */
class WorkSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@hrms.test')->firstOrFail();
        $hr = User::where('email', 'hr@hrms.test')->firstOrFail();
        $staff = User::whereHas('role', fn ($query) => $query->where('slug', Role::EMPLOYEE))->orderBy('id')->get();
        $everyone = $staff->concat([$admin, $hr]);

        $blueprint = [
            [
                'name' => 'Employee Onboarding Revamp',
                'code' => 'ONB',
                'description' => 'Cut time-to-productive for new joiners from three weeks to one.',
                'owner' => $hr,
                'tasks' => [
                    ['Draft the new joiner checklist', TaskStatus::Done, TaskPriority::High, -20],
                    ['Record the payroll walkthrough', TaskStatus::Done, TaskPriority::Medium, -12],
                    ['Build the buddy-matching sheet', TaskStatus::InReview, TaskPriority::Medium, 3],
                    ['Rewrite the offer letter template', TaskStatus::InProgress, TaskPriority::High, 6],
                    ['Collect week-one feedback form', TaskStatus::Todo, TaskPriority::Low, 14],
                    ['Chase outstanding ID documents', TaskStatus::Todo, TaskPriority::Urgent, -2],
                ],
            ],
            [
                'name' => 'Attendance Module',
                'code' => 'ATT',
                'description' => 'Check-in/check-out, monthly summaries and an export for payroll.',
                'owner' => $admin,
                'tasks' => [
                    ['Design the attendance schema', TaskStatus::Done, TaskPriority::High, -8],
                    ['Build the check-in endpoint', TaskStatus::InProgress, TaskPriority::Urgent, 2],
                    ['Monthly summary report', TaskStatus::InProgress, TaskPriority::Medium, 9],
                    ['Half-day and late-mark rules', TaskStatus::InReview, TaskPriority::High, 4],
                    ['Payroll CSV export', TaskStatus::Todo, TaskPriority::Medium, 18],
                    ['Backfill last quarter', TaskStatus::Todo, TaskPriority::Low, null],
                ],
            ],
            [
                'name' => 'Q4 Hiring Push',
                'code' => 'HIRE',
                'description' => 'Twelve roles across engineering and sales before the year closes.',
                'owner' => $hr,
                'tasks' => [
                    ['Publish the six engineering roles', TaskStatus::Done, TaskPriority::High, -15],
                    ['Shortlist senior backend candidates', TaskStatus::InProgress, TaskPriority::Urgent, 1],
                    ['Book the interview panel', TaskStatus::Todo, TaskPriority::High, 5],
                    ['Refresh the take-home exercise', TaskStatus::Todo, TaskPriority::Low, 21],
                    ['Agree sales comp bands', TaskStatus::InReview, TaskPriority::Medium, 7],
                ],
            ],
        ];

        foreach ($blueprint as $spec) {
            $project = Project::updateOrCreate(
                ['code' => $spec['code']],
                [
                    'name' => $spec['name'],
                    'description' => $spec['description'],
                    'owner_id' => $spec['owner']->id,
                    'status' => 'active',
                    'start_date' => now()->subMonths(2)->toDateString(),
                    'due_date' => now()->addMonths(2)->toDateString(),
                ],
            );

            $project->members()->sync($everyone->pluck('id'));

            foreach ($spec['tasks'] as $index => [$title, $status, $priority, $dueOffset]) {
                Task::updateOrCreate(
                    ['project_id' => $project->id, 'title' => $title],
                    [
                        'status' => $status,
                        'priority' => $priority,
                        'assigned_to' => $everyone[$index % $everyone->count()]->id,
                        'created_by' => $spec['owner']->id,
                        'due_date' => $dueOffset === null ? null : now()->addDays($dueOffset)->toDateString(),
                        'position' => $index,
                    ],
                );
            }
        }

        // A short thread on the most urgent task, so the detail page isn't bare.
        $task = Task::where('title', 'Chase outstanding ID documents')->first();

        if ($task && $task->comments()->count() === 0) {
            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $hr->id,
                'body' => 'Three joiners still have not uploaded proof of address. I have emailed all three.',
            ]);
            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $admin->id,
                'body' => 'Thanks — if they are not in by Friday, flag it and we will hold the payroll entry.',
            ]);
        }
    }
}
