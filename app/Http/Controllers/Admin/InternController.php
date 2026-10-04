<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InternRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use App\Services\OfferLetter;
use App\Services\Onboarding\EmployeeInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Interns, apart from staff: their own list and form under the Interns
 * permissions. Each is still an employee record with a login, so onboarding,
 * tasks and projects work for them exactly as for anyone else.
 */
class InternController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $archived = $request->boolean('archived');

        $interns = Employee::query()->interns()
            ->with(['user:id,name,email,is_active', 'department:id,name', 'designation:id,name'])
            ->when($archived, fn ($q) => $q->archived(), fn ($q) => $q->current())
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($q) => $q
                ->where('employee_code', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->when($request->string('stipend')->value(), fn ($query, string $s) => $query->where('has_stipend', $s === 'paid'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $e) => [
                ...$e->only('id', 'employee_code', 'date_of_joining', 'has_stipend', 'stipend', 'status', 'onboarding_status'),
                'user' => $e->user->only('id', 'name', 'email', 'is_active'),
                'department' => $e->department?->name,
                'designation' => $e->designation?->name,
                'can_manage' => ! $viewer->is($e->user) && $viewer->canGrant($e->user->permissions()),
            ]);

        return Inertia::render('admin/interns/index', [
            'interns' => $interns,
            'filters' => [...$request->only('search', 'stipend'), 'archived' => $archived],
            'counts' => [
                'current' => Employee::interns()->current()->count(),
                'archived' => Employee::interns()->archived()->count(),
                'paid' => Employee::interns()->current()->where('has_stipend', true)->count(),
            ],
            'canSeeProfiles' => $viewer->can('employees.view'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/interns/create', [
            ...$this->formOptions(),
            'nextCode' => Employee::nextCode(),
        ]);
    }

    public function store(InternRequest $request, EmployeeInvitations $invitations, OfferLetter $offerLetter): RedirectResponse
    {
        $data = $request->validated();

        $employee = DB::transaction(function () use ($data, $request, $offerLetter) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(40),
                'role_id' => $data['role_id'],
                'is_active' => true,
            ]);

            $employee = Employee::create([...$this->recordFields($data), 'user_id' => $user->id]);
            $employee->forceFill(['onboarding_status' => OnboardingStatus::Invited])->save();

            // The internship letter matches the stipend: one states it, the other says there is none.
            if ($data['offer_letter_mode'] === 'upload' && $request->hasFile('offer_letter')) {
                EmployeeController::storeOfferLetter($employee, $request->file('offer_letter'));
            } elseif ($data['offer_letter_mode'] === 'internship') {
                $offerLetter->generateFor($employee, OfferLetter::kindFor($employee));
            }

            return $employee;
        });

        if ($request->boolean('send_invite')) {
            $link = $invitations->send($employee->fresh());

            return to_route('admin.interns.index')
                ->with('success', "{$employee->user->name} has been added as an intern and invited.")
                ->with('invite_link', EmployeeInvitations::mailIsLocal() ? $link : null);
        }

        return to_route('admin.interns.index')->with('success', "{$employee->user->name} has been added as an intern.");
    }

    public function edit(Request $request, Employee $employee): Response
    {
        abort_unless($employee->isIntern(), 404);
        abort_unless($request->user()->canGrant($employee->user->permissions()), 403, 'This person has access you do not have.');

        return Inertia::render('admin/interns/edit', [
            'intern' => [
                ...$employee->only(
                    'id', 'department_id', 'designation_id', 'employee_code', 'phone', 'date_of_birth', 'gender',
                    'date_of_joining', 'has_stipend', 'stipend', 'address', 'status',
                ),
                // Plain Y-m-d, so the date input shows it and it saves back unchanged.
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
                'date_of_joining' => $employee->date_of_joining?->toDateString(),
                'name' => $employee->user->name,
                'email' => $employee->user->email,
            ],
            ...$this->formOptions($employee),
        ]);
    }

    public function update(InternRequest $request, Employee $employee): RedirectResponse
    {
        abort_unless($employee->isIntern(), 404);
        $data = $request->validated();

        DB::transaction(function () use ($employee, $data) {
            $employee->user->update(['name' => $data['name'], 'email' => $data['email']]);
            $employee->update($this->recordFields($data));
        });

        return to_route('admin.interns.index')->with('success', "{$employee->user->name} updated.");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recordFields(array $data): array
    {
        return collect($data)->except(['name', 'email', 'role_id', 'send_invite', 'offer_letter', 'offer_letter_mode'])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => Department::query()->selectable($employee?->department_id)->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->selectable($employee?->designation_id)->orderBy('name')->get(['id', 'name', 'department_id']),
        ];
    }
}
