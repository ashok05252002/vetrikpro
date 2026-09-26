<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Support\Cards;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Type-ahead over a project's tasks or test points, by number ("T-12", "12")
 * or by title. Used wherever work is linked: a test point's task, a branch's
 * task and testing points.
 */
class ReferenceLookupController extends Controller
{
    public function __invoke(Request $request, Project $project, string $kind): JsonResponse
    {
        $this->authorize('view', $project);

        $model = match ($kind) {
            'tasks' => Task::class,
            'test-points' => TestPoint::class,
            default => abort(404),
        };

        $search = $request->string('search')->trim()->value();
        $number = $search === '' ? null : $model::parseReference($search);

        $rows = $model::query()
            ->where('project_id', $project->id)
            ->with('assignee:id,name')
            ->when($model === TestPoint::class, fn ($q) => $q->with(['task:id,number,title', 'creator:id,name']))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->when($number, fn ($n) => $n->orWhere('number', $number))))
            // An exact number match first, then newest.
            ->when($number, fn ($q) => $q->orderByRaw('number = ? DESC', [$number]))
            ->orderByDesc('number')
            ->limit(20)
            ->get()
            ->map(fn ($row) => $row instanceof Task ? Cards::task($row) : Cards::testPoint($row));

        return response()->json(['data' => $rows]);
    }
}
