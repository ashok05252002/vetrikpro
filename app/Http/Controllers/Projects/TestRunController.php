<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TestResult;
use App\Enums\TestRunStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TestPoint;
use App\Models\TestRun;
use App\Models\TestRunResult;
use App\Services\BoardOrdering;
use App\Support\ProjectWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Test runs: a named round of testing over chosen points. Each run keeps its
 * own results, so "what failed in the last release round" is still answerable
 * after the points have been re-run since.
 */
class TestRunController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $runs = $project->testRuns()
            ->with(['creator:id,name', 'results:id,test_run_id,result'])
            ->orderByDesc('number')
            ->paginate(20)
            ->through(fn (TestRun $run) => $this->summary($run));

        return Inertia::render('testing/runs', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'counts' => ProjectWorkspace::testingCounts($project),
            'runs' => $runs,
            // What the "New run" dialog chooses from.
            'points' => $project->testPoints()->orderBy('number')->get(['id', 'number', 'title', 'status'])
                ->map(fn (TestPoint $p) => [...$p->only('id', 'title', 'status'), 'reference' => $p->reference()]),
            'can' => ['create' => $request->user()->can('create', new TestRun(['project_id' => $project->id]))],
        ]);
    }

    public function show(Request $request, Project $project, TestRun $testRun): Response
    {
        $this->authorize('view', $testRun);

        $user = $request->user();
        $testRun->load([
            'creator:id,name',
            'completer:id,name',
            'results.tester:id,name',
            'results.point:id,project_id,created_by,assigned_to,steps,expected_result,priority,status',
            'results.point.assignee:id,name',
        ]);
        $testRun->results->each(fn (TestRunResult $r) => $r->point?->setRelation('project', $project));

        return Inertia::render('testing/run', [
            'project' => ProjectWorkspace::header($project, $user),
            'counts' => ProjectWorkspace::testingCounts($project),
            'run' => [
                ...$this->summary($testRun),
                'description' => $testRun->description,
                'completer' => $testRun->completer?->only('id', 'name'),
            ],
            'results' => $testRun->results->map(fn (TestRunResult $r) => [
                ...$r->only('id', 'result', 'notes', 'tested_at'),
                'reference' => $r->reference(),
                'title' => $r->point_title,
                'tester' => $r->tester?->only('id', 'name'),
                'point' => $r->point ? [
                    ...$r->point->only('id', 'steps', 'expected_result', 'priority', 'status'),
                    'assignee' => $r->point->assignee?->only('id', 'name'),
                ] : null,
                'can_record' => $user->can('record', [$testRun, $r]),
            ]),
            'outcomes' => TestResult::options(),
            'can' => [
                'complete' => $user->can('complete', $testRun),
                'delete' => $user->can('delete', $testRun),
            ],
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $run = new TestRun(['project_id' => $project->id]);
        $this->authorize('create', $run);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'array', 'min:1'],
            // Only this project's points can go into its run.
            'points.*' => ['integer', 'distinct', Rule::exists('test_points', 'id')->where('project_id', $project->id)],
        ], [
            'points.required' => 'Choose at least one testing point.',
            'points.*.exists' => 'One of those testing points is not on this project.',
        ]);

        // In a transaction so the RUN number is allocated under lock.
        DB::transaction(function () use ($run, $data, $project, $request) {
            $run->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => TestRunStatus::Open,
                'created_by' => $request->user()->id,
            ])->save();

            $points = $project->testPoints()->whereKey($data['points'])->orderBy('number')->get(['id', 'number', 'title']);

            $run->results()->createMany($points->values()->map(fn (TestPoint $p, int $i) => [
                'test_point_id' => $p->id,
                'point_number' => $p->number,
                'point_title' => $p->title,
                'result' => TestResult::NotRun,
                'position' => $i,
            ]));
        });

        return to_route('testing.runs.show', [$project, $run])->with('success', "{$run->reference()} “{$run->name}” started.");
    }

    /**
     * Records one point's outcome in this run. Passed or Failed also moves the
     * point on its board (and so into its status log); Blocked and Not run
     * leave the board alone.
     */
    public function record(Request $request, Project $project, TestRun $testRun, TestRunResult $result): RedirectResponse
    {
        $result->setRelation('run', $testRun);
        $result->point?->setRelation('project', $project);

        if (! $testRun->isOpen()) {
            return back()->with('error', "{$testRun->reference()} is completed; reopen it to change results.");
        }

        $this->authorize('record', [$testRun, $result]);

        $data = $request->validate([
            'result' => ['required', Rule::enum(TestResult::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $outcome = TestResult::from($data['result']);

        DB::transaction(function () use ($result, $outcome, $data, $request) {
            $result->fill(['result' => $outcome, 'notes' => $data['notes'] ?? null]);
            $result->tested_by = $outcome === TestResult::NotRun ? null : $request->user()->id;
            $result->tested_at = $outcome === TestResult::NotRun ? null : now();
            $result->save();

            $status = $outcome->pointStatus();
            $point = $result->point;

            if ($status !== null && $point->status !== $status) {
                BoardOrdering::place($point, $status, BoardOrdering::nextPosition($point, $status));
            }
        });

        return back()->with('success', "{$result->reference()}: {$outcome->label()}.");
    }

    public function complete(Request $request, Project $project, TestRun $testRun): RedirectResponse
    {
        $this->authorize('complete', $testRun);

        // Guarded, so two people completing at once record one completion.
        TestRun::query()->whereKey($testRun->id)->where('status', TestRunStatus::Open)->update([
            'status' => TestRunStatus::Completed,
            'completed_by' => $request->user()->id,
            'completed_at' => now(),
        ]);

        return back()->with('success', "{$testRun->reference()} completed. Its results are now fixed.");
    }

    public function reopen(Project $project, TestRun $testRun): RedirectResponse
    {
        $this->authorize('complete', $testRun);

        $testRun->update(['status' => TestRunStatus::Open]);
        $testRun->forceFill(['completed_by' => null, 'completed_at' => null])->save();

        return back()->with('success', "{$testRun->reference()} reopened.");
    }

    public function destroy(Project $project, TestRun $testRun): RedirectResponse
    {
        $this->authorize('delete', $testRun);

        $reference = $testRun->reference();
        $testRun->delete();

        return to_route('testing.runs.index', $project)->with('success', "{$reference} deleted. The testing points themselves are unchanged.");
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(TestRun $run): array
    {
        $tally = $run->tally();
        $total = array_sum($tally);

        return [
            ...$run->only('id', 'number', 'name', 'status', 'created_at', 'completed_at'),
            'reference' => $run->reference(),
            'creator' => $run->creator?->only('id', 'name'),
            'tally' => $tally,
            'total' => $total,
        ];
    }
}
