<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Enums\TestResult;
use App\Enums\TestRunStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TestPoint;
use App\Models\User;
use App\Services\MergeRequestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

            // The first engineer reviews merges on every sample project.
            $project->members()->sync($everyone->mapWithKeys(fn (User $user) => [
                $user->id => ['role' => $user->is($staff->first()) ? 'dev_admin' : 'member'],
            ])->all());

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

        // A few bugs on the attendance project, across every column.
        $attendance = Project::where('code', 'ATT')->first();

        if ($attendance && $attendance->testPoints()->doesntExist()) {
            $points = [
                ['Check-in is refused outside office hours', 'closed'],
                ['Late marks appear on the monthly report', 'repeated'],
                ['Manager sees the whole team for the day', 'ready_for_test'],
                ['Regularisation request reaches the manager', 'in_progress'],
                ['Half-day leave shows as a full day', 'open'],
            ];

            foreach ($points as $position => [$title, $status]) {
                DB::transaction(fn () => TestPoint::create([
                    'project_id' => $attendance->id,
                    'task_id' => $attendance->tasks()->orderBy('number')->skip($position)->value('id'),
                    'title' => $title,
                    'steps' => "1. Sign in as an employee\n2. Open Attendance\n3. Try the action",
                    'expected_result' => 'The app behaves as the requirement describes.',
                    'status' => $status,
                    'priority' => 'medium',
                    'assigned_to' => $staff->first()?->id,
                    'created_by' => $admin->id,
                    'position' => 0,
                ]));
            }
        }

        // One test run in progress on the attendance project, its results
        // matching where the points already stand on the board. Written
        // directly: nobody is signed in, and a seed should never move cards.
        if ($attendance && $attendance->testRuns()->doesntExist() && $attendance->testPoints()->exists()) {
            DB::transaction(function () use ($attendance, $admin, $staff) {
                $run = $attendance->testRuns()->create([
                    'name' => 'Sprint 3 attendance checks',
                    'description' => 'Staging build of the late-marks work.',
                    'status' => TestRunStatus::Open,
                    'created_by' => $admin->id,
                ]);

                foreach ($attendance->testPoints()->orderBy('number')->get() as $position => $point) {
                    $result = match ($point->status) {
                        TestPointStatus::Closed => TestResult::Passed,
                        TestPointStatus::Repeated => TestResult::Failed,
                        default => TestResult::NotRun,
                    };

                    $row = $run->results()->make([
                        'test_point_id' => $point->id,
                        'point_number' => $point->number,
                        'point_title' => $point->title,
                        'result' => $result,
                        'notes' => $result === TestResult::Failed ? 'Late check-ins show on the daily view but not on the monthly report.' : null,
                        'position' => $position,
                    ]);
                    $row->tested_by = $result === TestResult::NotRun ? null : $staff->first()?->id;
                    $row->tested_at = $result === TestResult::NotRun ? null : now()->subDay();
                    $row->save();
                }
            });
        }

        // One branch waiting for review on the attendance project: Meera asks,
        // Arun (the seeded dev admin) reviews.
        $meera = User::where('email', 'meera@hrms.test')->first();

        if ($attendance && $meera && $attendance->branches()->doesntExist()) {
            $branch = $attendance->branches()->create([
                'name' => 'feature/attendance-late-marks',
                'base_branch' => $attendance->default_branch,
                'description' => "Flags check-ins after 09:45 as late and shows them on the monthly report.\nAdds a grace period setting.",
                'status' => 'active',
                'created_by' => $meera->id,
            ]);
            $branch->tasks()->sync($attendance->tasks()->orderBy('number')->limit(2)->pluck('id'));
            $branch->testPoints()->sync($attendance->testPoints()->orderBy('number')->limit(2)->pluck('id'));

            app(MergeRequestService::class)->open($branch, $meera, [
                'title' => 'Late marks on the attendance report',
                'description' => 'TP-2 still fails on months with a holiday; looking at it now.',
                'target_branch' => $attendance->default_branch,
            ]);
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
