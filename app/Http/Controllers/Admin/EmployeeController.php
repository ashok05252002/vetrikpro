<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = Employee::query()
            ->with(['user:id,name,email', 'department:id,name', 'designation:id,name'])
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")));
            })
            ->when($request->string('department')->value(), fn ($query, string $id) => $query->where('department_id', $id))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/employees/index', [
            'employees' => $employees,
            'departments' => $this->departments(),
            'filters' => $request->only('search', 'department'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/employees/create', [
            ...$this->formOptions(),
            'nextCode' => Employee::nextCode(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());

        return to_route('admin.employees.index')->with('success', 'Employee profile created.');
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['user:id,name,email,role_id,is_active', 'department:id,name', 'designation:id,name']);

        return Inertia::render('admin/employees/show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('admin/employees/edit', [
            'employee' => $employee->only(
                'id', 'user_id', 'department_id', 'designation_id', 'employee_code', 'phone',
                'date_of_birth', 'gender', 'date_of_joining', 'employment_type', 'salary', 'address', 'status',
            ),
            ...$this->formOptions($employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return to_route('admin.employees.index')->with('success', 'Employee profile updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return to_route('admin.employees.index')->with('success', 'Employee profile deleted.');
    }

    /**
     * Dropdown data. Only users without a profile are assignable — plus the
     * one already attached to the employee being edited.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Employee $employee = null): array
    {
        return [
            'users' => User::query()
                ->whereDoesntHave('employee')
                ->when($employee, fn ($query) => $query->orWhere('id', $employee->user_id))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'departments' => $this->departments(),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name', 'department_id']),
        ];
    }

    private function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }
}
