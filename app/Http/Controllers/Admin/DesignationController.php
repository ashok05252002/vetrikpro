<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DesignationRequest;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    public function index(Request $request): Response
    {
        $designations = Designation::query()
            ->with('department:id,name')
            ->withCount('employees')
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Designation $designation) => [...$designation->toArray(), 'in_use' => $designation->isInUse()]);

        return Inertia::render('admin/designations/index', [
            'designations' => $designations,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/designations/create', [
            'departments' => $this->departments(),
        ]);
    }

    public function store(DesignationRequest $request): RedirectResponse
    {
        Designation::create($request->validated());

        return to_route('admin.designations.index')->with('success', 'Designation created.');
    }

    public function edit(Designation $designation): Response
    {
        return Inertia::render('admin/designations/edit', [
            'designation' => $designation->only('id', 'department_id', 'name', 'description'),
            'departments' => $this->departments($designation->department_id),
        ]);
    }

    public function update(DesignationRequest $request, Designation $designation): RedirectResponse
    {
        $designation->update($request->validated());

        return to_route('admin.designations.index')->with('success', 'Designation updated.');
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        if ($designation->isInUse()) {
            return back()->with('error', "“{$designation->name}” is in use. Mark it inactive instead, so it stops being offered.");
        }

        $designation->delete();

        return to_route('admin.designations.index')->with('success', 'Designation deleted.');
    }

    /**
     * Switch a designation on or off. Off takes it out of pickers; everyone
     * who holds it keeps it.
     */
    public function active(Request $request, Designation $designation): RedirectResponse
    {
        $designation->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('success', $designation->is_active ? "“{$designation->name}” is active again." : "“{$designation->name}” is now inactive.");
    }

    /**
     * @return Collection<int, Department>
     */
    private function departments(?int $keep = null)
    {
        return Department::query()->selectable($keep)->orderBy('name')->get(['id', 'name']);
    }
}
