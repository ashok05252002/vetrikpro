<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Onboarding\EmployeeInvitations;
use App\Services\Onboarding\OnboardingChecklist;
use App\Support\EmployeeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->when($request->string('onboarding')->value(), fn ($query, string $status) => $query->where('onboarding_status', $status))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            // Onboarding progress per row: two small queries each, on ten rows.
            ->through(fn (Employee $employee) => [
                ...$employee->toArray(),
                'onboarding' => $employee->onboarding_status === null ? null : [
                    'status' => $employee->onboarding_status->value,
                    ...OnboardingChecklist::progress($employee),
                ],
            ]);

        return Inertia::render('admin/employees/index', [
            'employees' => $employees,
            'departments' => $this->departments(),
            'onboardingStatuses' => OnboardingStatus::options(),
            'filters' => $request->only('search', 'department', 'onboarding'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/employees/create', [
            ...$this->formOptions(),
            'nextCode' => Employee::nextCode(),
        ]);
    }

    public function store(EmployeeRequest $request, EmployeeInvitations $invitations): RedirectResponse
    {
        $data = $request->validated();
        $isNew = $data['mode'] === 'new';

        $employee = DB::transaction(function () use ($data, $isNew, $request) {
            if ($isNew) {
                // The account starts with a random password nobody knows; the
                // person sets their own from the invite link.
                $data['user_id'] = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Str::random(40),
                    'role_id' => Role::where('slug', Role::EMPLOYEE)->value('id'),
                    'is_active' => true,
                ])->id;
            }

            $employee = Employee::create(collect($data)->except(['mode', 'name', 'email', 'send_invite', 'offer_letter'])->all());

            if ($isNew) {
                $employee->forceFill(['onboarding_status' => OnboardingStatus::Invited])->save();
            }

            if ($request->hasFile('offer_letter')) {
                $this->storeOfferLetter($employee, $request->file('offer_letter'));
            }

            return $employee;
        });

        if ($isNew && $request->boolean('send_invite')) {
            // After commit: an email about an account that rolled back would be worse than none.
            $link = $invitations->send($employee->fresh());

            return to_route('admin.employees.onboarding', $employee)
                ->with('success', "{$employee->user->name} has been invited.")
                ->with('invite_link', EmployeeInvitations::mailIsLocal() ? $link : null);
        }

        return to_route($isNew ? 'admin.employees.onboarding' : 'admin.employees.show', $employee)->with('success', 'Employee profile created.');
    }

    /**
     * The offer letter HR sends with the invite, kept with the employee's
     * other files on the private disk.
     */
    public static function storeOfferLetter(Employee $employee, UploadedFile $file): void
    {
        $old = $employee->offer_letter_path;

        $employee->forceFill([
            'offer_letter_path' => $file->store(EmployeeDocument::directoryFor($employee->id).'/offer-letter', EmployeeDocument::DISK),
            'offer_letter_name' => $file->getClientOriginalName(),
        ])->save();

        if ($old) {
            Storage::disk(EmployeeDocument::DISK)->delete($old);
        }
    }

    public function show(Request $request, Employee $employee): Response
    {
        $employee->load(['user:id,name,email,role_id,is_active', 'department:id,name', 'designation:id,name']);

        return Inertia::render('admin/employees/show', [
            'employee' => $employee,
            'profile' => EmployeeProfile::header($employee, $request->user()),
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
