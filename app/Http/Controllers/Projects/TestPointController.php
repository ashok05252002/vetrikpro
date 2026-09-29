<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TaskPriority;
use App\Enums\TestPointStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TaskController;
use App\Http\Requests\TestPointRequest;
use App\Models\Project;
use App\Models\TestPoint;
use App\Models\User;
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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A project's testing points in the Testing module: on a board like tasks,
 * or as a filterable list. Runs over these points live in TestRunController.
 */
class TestPointController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        return Inertia::render('testing/points', $this->board($request, $project));
    }

    /**
     * The same bugs as the Testing module, shown in the project's own Testing
     * tab so working on a project never leaves it.
     */
    public function project(Request $request, Project $project): Response
    {
        return Inertia::render('projects/testing', $this->board($request, $project));
    }

    /**
     * @return array<string, mixed>
     */
    private function board(Request $request, Project $project): array
    {
        $this->authorize('view', $project);

        $view = $request->string('view')->value() === 'list' ? 'list' : 'board';
        $base = $project->testPoints()->with(['assignee:id,name', 'creator:id,name', 'assigner:id,name', 'task:id,number,title', 'project:id,owner_id']);
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

        return [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'counts' => ProjectWorkspace::testingCounts($project),
            'view' => $view,
            ...$payload,
            // Counts per outcome for the summary strip; cheap, one grouped query.
            'summary' => $project->testPoints()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => TestPointStatus::options(),
            'priorities' => TaskPriority::options(),
            'assignees' => ProjectPeople::assignable($project),
            'filters' => $request->only('search', 'status', 'priority', 'assignee'),
            'can' => [
                'create' => $request->user()->can('create', new TestPoint(['project_id' => $project->id])),
                'assign' => (new TestPoint)->setRelation('project', $project)->assignableBy($request->user()),
            ],
        ];
    }

    public function show(Request $request, Project $project, TestPoint $testPoint): Response
    {
        $this->authorize('view', $testPoint);

        $testPoint->load(['assignee:id,name', 'creator:id,name', 'assigner:id,name', 'lastTester:id,name', 'task:id,number,title,status']);

        return Inertia::render('testing/point', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'counts' => ProjectWorkspace::testingCounts($project),
            'point' => [
                ...Cards::testPoint($testPoint, $request->user()),
                'history' => Cards::history($testPoint),
                'attachments' => $testPoint->attachments()->with('uploader:id,name')->get()->map(fn ($a) => [
                    ...$a->only('id', 'original_name', 'size', 'created_at'),
                    'uploaded_by' => $a->uploader?->only('id', 'name'),
                    'url' => route('testing.points.attachments.show', [$project, $testPoint, $a]),
                    'can_delete' => $a->uploaded_by === $request->user()->id || $request->user()->can('projects.edit') || $project->isLedBy($request->user()),
                ]),
                ...$testPoint->only('steps', 'expected_result', 'actual_result', 'assigned_to', 'task_id', 'created_at'),
                'creator' => $testPoint->creator?->only('id', 'name'),
                'last_tester' => $testPoint->lastTester?->only('id', 'name'),
                // Its outcome in every run it was part of, newest first.
                'runs' => $testPoint->runResults()->with(['run:id,project_id,number,name,status', 'tester:id,name'])->get()->map(fn ($r) => [
                    ...$r->only('id', 'result', 'notes', 'tested_at'),
                    'run' => [...$r->run->only('id', 'name', 'status'), 'reference' => $r->run->reference()],
                    'tester' => $r->tester?->only('id', 'name'),
                ]),
            ],
            'statuses' => TestPointStatus::options(),
            'priorities' => TaskPriority::options(),
            'assignees' => ProjectPeople::assignable($project),
            'can' => [
                'update' => $request->user()->can('update', $testPoint),
                'changeStatus' => $request->user()->can('move', $testPoint),
                'assign' => $request->user()->can('assign', $testPoint),
                'delete' => $request->user()->can('delete', $testPoint),
            ],
        ]);
    }

    public function store(TestPointRequest $request, Project $project): RedirectResponse
    {
        $point = new TestPoint([...$request->validated(), 'project_id' => $project->id]);
        $this->authorize('create', $point);

        $point->created_by = $request->user()->id;
        // A new bug has nobody yet, so naming anyone at all is an assignment.
        self::guardAssignment($request->user(), $point, $request->validated('assigned_to'), null);

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
        self::guardAssignment($request->user(), $testPoint, $request->validated('assigned_to'), $testPoint->assigned_to);

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

        return to_route('testing.points.index', $project)->with('success', "{$reference} deleted.");
    }

    /**
     * Anyone on the project reports a bug; only the team leader (testing.assign),
     * the project owner or an administrator decides who it is for. Sending the
     * current assignee back unchanged is not an assignment.
     */
    private static function guardAssignment(User $user, TestPoint $point, mixed $assignee, ?int $current): void
    {
        $to = $assignee === null || $assignee === '' ? null : (int) $assignee;

        if ($to !== $current && ! $point->assignableBy($user)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Only a team leader, the project owner or an administrator can assign a bug.',
            ]);
        }
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
