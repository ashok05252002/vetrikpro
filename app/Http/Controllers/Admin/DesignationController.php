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
            ->withQueryString();

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
            'departments' => $this->departments(),
        ]);
    }

    public function update(DesignationRequest $request, Designation $designation): RedirectResponse
    {
        $designation->update($request->validated());

        return to_route('admin.designations.index')->with('success', 'Designation updated.');
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $designation->delete();

        return to_route('admin.designations.index')->with('success', 'Designation deleted.');
    }

    /**
     * @return Collection<int, Department>
     */
    private function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }
}
