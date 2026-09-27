<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $departments = Department::query()
            ->withCount(['designations', 'employees'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Department $department) => [...$department->toArray(), 'in_use' => $department->isInUse()]);

        return Inertia::render('admin/departments/index', [
            'departments' => $departments,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/departments/create');
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return to_route('admin.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('admin/departments/edit', [
            'department' => $department->only('id', 'name', 'code', 'description'),
        ]);
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return to_route('admin.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->isInUse()) {
            return back()->with('error', "“{$department->name}” is in use. Mark it inactive instead, so it stops being offered.");
        }

        $department->delete();

        return to_route('admin.departments.index')->with('success', 'Department deleted.');
    }

    /**
     * Switch a department on or off. Off takes it out of pickers; everyone
     * already in it stays in it.
     */
    public function active(Request $request, Department $department): RedirectResponse
    {
        $department->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('success', $department->is_active ? "“{$department->name}” is active again." : "“{$department->name}” is now inactive.");
    }
}
