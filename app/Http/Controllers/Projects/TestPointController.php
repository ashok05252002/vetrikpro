<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TaskPriority;
use App\Enums\TestPointStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TaskController;
use App\Http\Requests\TestPointRequest;
use App\Models\Project;
use App\Models\TestPoint;
use App\Services\BoardOrdering;
use App\Support\Cards;
use App\Support\ProjectPeople;
use App\Support\ProjectWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project's Testing tab: test points on a board like tasks, or as a
 * filterable list.
 */
class TestPointController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $view = $request->string('view')->value() === 'list' ? 'list' : 'board';
        $base = $project->testPoints()->with(['assignee:id,name', 'task:id,number,title', 'project:id,owner_id']);
        $viewer = $request->user();

        $payload = $view === 'board'
            ? [
                'columns' => collect(TestPointStatus::cases())->map(fn (TestPointStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'items' => (clone $base)->where('status', $status)->orderBy('position')->get()->map(fn ($p) => Cards::testPoint($p, $viewer))->values(),
                ]),
            ]
            : [
                'list' => $this->filtered($base, $request)
                    ->orderBy('number')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (TestPoint $p) => Cards::testPoint($p, $viewer)),
            ];

        return Inertia::render('projects/testing', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'view' => $view,
            ...$payload,
            // Counts per outcome for the summary strip; cheap, one grouped query.
            'summary' => $project->testPoints()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => TestPointStatus::options(),
            'priorities' => TaskPriority::options(),
            'assignees' => ProjectPeople::assignable($project),
            'filters' => $request->only('search', 'status', 'priority', 'assignee'),
            'can' => ['create' => $request->user()->can('create', new TestPoint(['project_id' => $project->id]))],
        ]);
    }

    public function show(Request $request, Project $project, TestPoint $testPoint): Response
    {
        $this->authorize('view', $testPoint);

        $testPoint->load(['assignee:id,name', 'creator:id,name', 'lastTester:id,name', 'task:id,number,title,status']);

        return Inertia::render('projects/test-point', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'point' => [
                ...Cards::testPoint($testPoint, $request->user()),
                'history' => Cards::history($testPoint),
                ...$testPoint->only('steps', 'expected_result', 'actual_result', 'assigned_to', 'task_id', 'created_at'),
                'creator' => $testPoint->creator?->only('id', 'name'),
                'last_tester' => $testPoint->lastTester?->only('id', 'name'),
            ],
            'statuses' => TestPointStatus::options(),
            'priorities' => TaskPriority::options(),
            'assignees' => ProjectPeople::assignable($project),
            'can' => [
                'update' => $request->user()->can('update', $testPoint),
                'changeStatus' => $request->user()->can('move', $testPoint),
                'delete' => $request->user()->can('delete', $testPoint),
            ],
        ]);
    }

    public function store(TestPointRequest $request, Project $project): RedirectResponse
    {
        $point = new TestPoint([...$request->validated(), 'project_id' => $project->id]);
        $this->authorize('create', $point);

        $point->created_by = $request->user()->id;

        // In a transaction so the TP number is allocated under lock.
        DB::transaction(function () use ($point) {
            $point->position = BoardOrdering::nextPosition($point, $point->status);
            $point->save();
        });

        return back()->with('success', "{$point->reference()} “{$point->title}” created.");
    }

    public function update(TestPointRequest $request, Project $project, TestPoint $testPoint): RedirectResponse
    {
        $this->authorize('update', $testPoint);
        TaskController::guardStatus($request->user(), $testPoint, $request->validated('status'));

        $testPoint->update($request->validated());

        return back()->with('success', "{$testPoint->reference()} updated.");
    }

    public function move(Request $request, Project $project, TestPoint $testPoint): RedirectResponse
    {
        $this->authorize('move', $testPoint);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TestPointStatus::class)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        BoardOrdering::place($testPoint, TestPointStatus::from($data['status']), $data['position']);

        return back();
    }

    public function destroy(Project $project, TestPoint $testPoint): RedirectResponse
    {
        $this->authorize('delete', $testPoint);

        $reference = $testPoint->reference();
        $testPoint->delete();

        return to_route('projects.testing.index', $project)->with('success', "{$reference} deleted.");
    }

    /**
     * @param  Builder<TestPoint>|HasMany<TestPoint, Project>  $query
     */
    private function filtered($query, Request $request)
    {
        return $query
            ->when($request->string('search')->trim()->value(), function ($q, string $search) {
                $number = TestPoint::parseReference($search);
                $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->when($number, fn ($n) => $n->orWhere('number', $number)));
            })
            ->when($request->string('status')->value(), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->string('priority')->value(), fn ($q, string $priority) => $q->where('priority', $priority))
            ->when($request->integer('assignee'), fn ($q, int $id) => $q->where('assigned_to', $id));
    }
}
