<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\OfferLetter;
use App\Services\Onboarding\EmployeeInvitations;
use App\Services\Onboarding\OnboardingChecklist;
use App\Support\EmployeeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employees, which are also the app's users: every person has a login (users)
 * and an HR record (employees), and both are managed here, together. The
 * tables stay separate because sign-in and HR data are different concerns,
 * but nothing in the app creates, edits or deletes one without the other.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $archived = $request->boolean('archived');

        $employees = Employee::query()
            // Interns have their own page.
            ->staff()
            ->with(['user:id,name,email,role_id,is_active', 'user.role:id,name,slug,is_super', 'department:id,name', 'designation:id,name'])
            // Archived people have their own view; the default list is the current staff.
            ->when($archived, fn ($query) => $query->archived(), fn ($query) => $query->current())
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")));
            })
            ->when($request->string('department')->value(), fn ($query, string $id) => $query->where('department_id', $id))
            ->when($request->string('role')->value(), fn ($query, string $slug) => $query->whereHas('user.role', fn ($r) => $r->where('slug', $slug)))
            ->when($request->string('account')->value(), fn ($query, string $account) => $query->whereHas('user', fn ($u) => $u->where('is_active', $account === 'active')))
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
                'can_toggle_access' => $viewer->can('employees.edit') && ! $viewer->is($employee->user) && $viewer->canGrant($employee->user->permissions()),
            ]);

        return Inertia::render('admin/employees/index', [
            'employees' => $employees,
            'departments' => $this->departments(),
            'roles' => Role::orderBy('name')->get()->map(fn (Role $role) => ['value' => $role->slug, 'label' => $role->name]),
            'onboardingStatuses' => OnboardingStatus::options(),
            'filters' => [...$request->only('search', 'department', 'role', 'account', 'onboarding'), 'archived' => $archived],
            'archivedCount' => Employee::staff()->archived()->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/employees/create', [
            ...$this->formOptions(),
            'roles' => Role::orderBy('name')->get()
                ->filter(fn (Role $role) => $request->user()->canAssignRole($role))
                ->map(fn (Role $role) => ['value' => (string) $role->id, 'label' => $role->name])
                ->values(),
            'defaultRoleId' => (string) Role::where('slug', Role::EMPLOYEE)->value('id'),
            'nextCode' => Employee::nextCode(),
        ]);
    }

    public function store(EmployeeRequest $request, EmployeeInvitations $invitations, OfferLetter $offerLetter): RedirectResponse
    {
        $data = $request->validated();
        $mode = $data['offer_letter_mode'] ?? ($request->hasFile('offer_letter') ? 'upload' : 'none');

        $employee = DB::transaction(function () use ($data, $request, $mode, $offerLetter) {
            // The login starts with a random password nobody knows; the person
            // sets their own from the invite link.
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(40),
                'role_id' => $data['role_id'] ?? Role::where('slug', Role::EMPLOYEE)->value('id'),
                'is_active' => true,
            ]);

            $employee = Employee::create([...$this->recordFields($data), 'user_id' => $user->id]);
            $employee->forceFill(['onboarding_status' => OnboardingStatus::Invited])->save();

            if ($mode === 'upload' && $request->hasFile('offer_letter')) {
                self::storeOfferLetter($employee, $request->file('offer_letter'));
            } elseif ($mode === 'generate') {
                $offerLetter->generateFor($employee, OfferLetter::OFFER);
            } elseif ($mode === 'welcome') {
                $offerLetter->generateFor($employee, OfferLetter::WELCOME);
            }

            return $employee;
        });

        if ($request->boolean('send_invite')) {
            // After commit: an email about an account that rolled back would be worse than none.
            $link = $invitations->send($employee->fresh());

            return to_route('admin.employees.onboarding', $employee)
                ->with('success', "{$employee->user->name} has been added and invited.")
                ->with('invite_link', EmployeeInvitations::mailIsLocal() ? $link : null);
        }

        return to_route('admin.employees.onboarding', $employee)->with('success', "{$employee->user->name} has been added. Send the invite when you're ready.");
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
            'offer_letter_kind' => null,
        ])->save();

        if ($old) {
            Storage::disk(EmployeeDocument::DISK)->delete($old);
        }
    }

    public function show(Request $request, Employee $employee): Response
    {
        $employee->load(['user:id,name,email,role_id,is_active', 'department:id,name', 'designation:id,name']);

        $canPromote = $request->user()->can('employees.promote') && ! $request->user()->is($employee->user) && $request->user()->canGrant($employee->user->permissions());

        return Inertia::render('admin/employees/show', [
            'employee' => $employee,
            'profile' => EmployeeProfile::header($employee, $request->user()),
            'promotions' => $employee->promotions()->with('creator:id,name')->get()->map(fn (Promotion $p) => [
                ...$p->only('id', 'from_designation_name', 'to_designation_name', 'from_salary', 'to_salary', 'effective_date', 'note', 'emailed_at', 'created_at'),
                'is_promotion' => $p->isDesignationChange(),
                'increment_percent' => $p->incrementPercent(),
                'creator' => $p->creator?->only('id', 'name'),
                'letter_url' => $p->letter_path ? route('admin.employees.promotions.letter', [$employee, $p]) : null,
            ]),
            // Only what the Promote dialog needs, and only for those who may use it.
            'promoteOptions' => $canPromote && ! $employee->isArchived() ? $this->formOptions($employee) : null,
        ]);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        abort_unless($request->user()->canGrant($employee->user->permissions()), 403, 'This person has access you do not have.');

        return Inertia::render('admin/employees/edit', [
            'employee' => [
                ...$employee->only(
                    'id', 'department_id', 'designation_id', 'employee_code', 'phone',
                    'date_of_birth', 'gender', 'date_of_joining', 'employment_type', 'salary', 'address', 'status',
                ),
                'name' => $employee->user->name,
                'email' => $employee->user->email,
            ],
            ...$this->formOptions($employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($employee, $data) {
            $employee->user->update(['name' => $data['name'], 'email' => $data['email']]);
            $employee->update($this->recordFields($data));
        });

        return to_route('admin.employees.show', $employee)->with('success', 'Employee updated.');
    }

    /**
     * Deleting an employee removes the person: their login, HR record and
     * files together. Only for someone who never touched the work — a mistaken
     * entry. Anyone with history is archived instead, so nothing they did
     * loses its name.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        abort_unless($request->user()->canGrant($user->permissions()), 403, 'This person has access you do not have.');

        if ($user->hasWorkHistory()) {
            return back()->with('error', "{$user->name} has tasks, bugs or project history. Archive them instead, so that history keeps their name.");
        }

        $name = $user->name;
        // The employee row goes by cascade; User's deleting hook clears the files.
        $user->delete();

        return to_route($employee->isIntern() ? 'admin.interns.index' : 'admin.employees.index')->with('success', "{$name} was deleted.");
    }

    /**
     * Archive someone who has left: they can no longer sign in, and they leave
     * the employee list and every picker — but their records, documents and
     * work history stay, under their name.
     */
    public function archive(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot archive yourself.');
        }

        abort_unless($request->user()->canGrant($user->permissions()), 403, 'This person has access you do not have.');

        DB::transaction(function () use ($employee, $user, $request) {
            $employee->forceFill(['archived_at' => now(), 'archived_by' => $request->user()->id])->save();
            $user->forceFill(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $request->user()->id])->save();
        });

        return back()->with('success', "{$user->name} was archived and can no longer sign in.");
    }

    /**
     * Bring an archived person back, with their sign-in switched on again.
     */
    public function restore(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        abort_unless($request->user()->canGrant($user->permissions()), 403, 'This person has access you do not have.');

        DB::transaction(function () use ($employee, $user) {
            $employee->forceFill(['archived_at' => null, 'archived_by' => null])->save();
            $user->forceFill(['is_active' => true, 'deactivated_at' => null, 'deactivated_by' => null])->save();
        });

        // Interns go back to their list: their manager may not see staff profiles.
        return ($employee->isIntern() ? to_route('admin.interns.index', ['archived' => 1]) : to_route('admin.employees.show', $employee))
            ->with('success', "{$user->name} is back and can sign in again.");
    }

    /**
     * Switch someone's portal access on or off. Off ends their session on
     * their next request (EnsureUserIsActive) and refuses future logins.
     */
    public function status(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $user = $employee->user;

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }

        abort_unless($request->user()->canGrant($user->permissions()), 403, 'This person has access you do not have.');

        $active = (bool) $data['is_active'];

        if ($active && $employee->isArchived()) {
            return back()->with('error', "{$user->name} is archived. Restore them to let them sign in again.");
        }

        $user->forceFill([
            'is_active' => $active,
            'deactivated_at' => $active ? null : now(),
            'deactivated_by' => $active ? null : $request->user()->id,
        ])->save();

        return back()->with('success', $active ? "{$user->name} can use the portal again." : "{$user->name} can no longer sign in.");
    }

    /**
     * Email the person a link to choose a new password. Nobody types a
     * password on someone else's behalf.
     */
    public function sendPasswordReset(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        abort_unless($request->user()->canGrant($user->permissions()), 403, 'This person has access you do not have.');

        $status = Password::sendResetLink(['email' => $user->email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', "A password reset link was emailed to {$user->email}.")
            : back()->with('error', __($status));
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

    private function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }
}
