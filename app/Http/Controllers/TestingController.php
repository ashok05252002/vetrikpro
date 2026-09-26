<?php

namespace App\Http\Controllers;

use App\Enums\TestPointStatus;
use App\Enums\TestRunStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Testing module's front page: every project the person can see, with
 * where its testing stands. Opening one goes to that project's testing points.
 */
class TestingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::query()
            ->visibleTo($user)
            ->with(['latestTestRun.creator:id,name'])
            ->withCount([
                'testPoints',
                'testPoints as open_count' => fn ($q) => $q->where('status', '!=', TestPointStatus::Closed),
                'testPoints as ready_count' => fn ($q) => $q->where('status', TestPointStatus::ReadyForTest),
                'testPoints as repeated_count' => fn ($q) => $q->where('status', TestPointStatus::Repeated),
                'testRuns as open_runs_count' => fn ($q) => $q->where('status', TestRunStatus::Open),
            ])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                ...$project->only('id', 'name', 'code', 'status'),
                'points_count' => $project->test_points_count,
                'open_count' => $project->open_count,
                'ready_count' => $project->ready_count,
                'repeated_count' => $project->repeated_count,
                'open_runs_count' => $project->open_runs_count,
                'latest_run' => $project->latestTestRun ? [
                    ...$project->latestTestRun->only('id', 'name', 'status', 'created_at', 'completed_at'),
                    'reference' => $project->latestTestRun->reference(),
                    'tally' => $project->latestTestRun->tally(),
                ] : null,
            ]);

        return Inertia::render('testing/index', [
            'projects' => $projects,
            'filters' => $request->only('search'),
        ]);
    }
}
