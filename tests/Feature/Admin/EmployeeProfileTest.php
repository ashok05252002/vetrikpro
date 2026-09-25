<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(EmployeeDocument::DISK);
    }

    private function upload(User $actor, Employee $employee, array $overrides = [])
    {
        return $this->actingAs($actor)->post(route('admin.employees.documents.store', $employee), [
            'type' => 'id_proof',
            'title' => 'Aadhaar card',
            'file' => UploadedFile::fake()->create('aadhaar.pdf', 200, 'application/pdf'),
            ...$overrides,
        ]);
    }

    // Tabs and visibility

    public function test_the_overview_carries_the_profile_header()
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.employees.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/employees/show')
                ->where('profile.name', $employee->user->name)
                ->where('profile.viewer.can_documents', true)
                // HR does not manage roles, so the Access tab stays hidden.
                ->where('profile.viewer.can_access', false));
    }

    public function test_the_projects_tab_lists_their_projects_with_their_role()
    {
        $employee = Employee::factory()->create();
        $mine = Project::factory()->create(['name' => 'Mine']);
        $mine->members()->attach($employee->user_id, ['role' => 'dev_admin']);
        Project::factory()->create(['name' => 'Not mine']);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.employees.projects', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Mine')
                ->where('projects.data.0.project_role', 'dev_admin'));
    }

    public function test_the_tasks_tab_lists_open_work_first()
    {
        $employee = Employee::factory()->create();
        Task::factory()->create(['assigned_to' => $employee->user_id, 'title' => 'Finished', 'status' => 'done']);
        Task::factory()->create(['assigned_to' => $employee->user_id, 'title' => 'Open', 'status' => 'todo']);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.employees.tasks', $employee))
            ->assertInertia(fn (Assert $page) => $page->where('tasks.data.0.title', 'Open')->has('tasks.data', 2));
    }

    // Documents

    public function test_hr_can_upload_and_download_a_document()
    {
        $hr = User::factory()->hr()->create();
        $employee = Employee::factory()->create();

        $this->upload($hr, $employee)->assertSessionHas('success');

        $document = $employee->documents()->first();
        $this->assertSame('aadhaar.pdf', $document->original_name);
        $this->assertSame($hr->id, $document->uploaded_by);
        // Stored under a random name inside the employee's own folder.
        $this->assertStringStartsWith("employees/{$employee->id}/", $document->file_path);
        $this->assertNotSame("employees/{$employee->id}/aadhaar.pdf", $document->file_path);
        Storage::disk(EmployeeDocument::DISK)->assertExists($document->file_path);

        $this->actingAs($hr)
            ->get(route('admin.employees.documents.download', [$employee, $document]))
            ->assertOk()
            ->assertDownload('aadhaar.pdf');
    }

    public function test_documents_need_the_documents_permission()
    {
        $employee = Employee::factory()->create();
        $this->upload(User::factory()->hr()->create(), $employee);
        $document = $employee->documents()->first();

        // Can see profiles, but not documents.
        $viewer = User::factory()->create();
        $viewer->syncPermissionOverrides(['employees.view' => true]);

        $this->actingAs($viewer)->get(route('admin.employees.show', $employee))->assertOk();
        $this->actingAs($viewer)->get(route('admin.employees.documents.index', $employee))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.employees.documents.download', [$employee, $document]))->assertForbidden();

        // The employee themself has no admin access at all.
        $this->actingAs($employee->user)->get(route('admin.employees.documents.download', [$employee, $document]))->assertForbidden();
    }

    public function test_a_document_is_only_reachable_through_its_own_employee()
    {
        $hr = User::factory()->hr()->create();
        $owner = Employee::factory()->create();
        $other = Employee::factory()->create();
        $this->upload($hr, $owner);
        $document = $owner->documents()->first();

        $this->actingAs($hr)->get(route('admin.employees.documents.download', [$other, $document]))->assertNotFound();
        $this->actingAs($hr)->delete(route('admin.employees.documents.destroy', [$other, $document]))->assertNotFound();
    }

    public function test_disallowed_and_oversized_files_are_rejected()
    {
        $hr = User::factory()->hr()->create();
        $employee = Employee::factory()->create();

        $this->upload($hr, $employee, ['file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->upload($hr, $employee, ['file' => UploadedFile::fake()->create('huge.pdf', 20000, 'application/pdf')])->assertSessionHasErrors('file');

        $this->assertSame(0, $employee->documents()->count());
    }

    public function test_deleting_a_document_removes_its_file()
    {
        $hr = User::factory()->hr()->create();
        $employee = Employee::factory()->create();
        $this->upload($hr, $employee);
        $document = $employee->documents()->first();

        $this->actingAs($hr)->delete(route('admin.employees.documents.destroy', [$employee, $document]))->assertSessionHas('success');

        $this->assertNull($document->fresh());
        Storage::disk(EmployeeDocument::DISK)->assertMissing($document->file_path);
    }

    public function test_deleting_the_user_account_removes_their_files()
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $this->upload($admin, $employee);
        $path = $employee->documents()->first()->file_path;

        $this->actingAs($admin)->delete(route('admin.users.destroy', $employee->user));

        Storage::disk(EmployeeDocument::DISK)->assertMissing($path);
        $this->assertSame(0, EmployeeDocument::count());
    }

    public function test_documents_filter_by_type()
    {
        $hr = User::factory()->hr()->create();
        $employee = Employee::factory()->create();
        $this->upload($hr, $employee);
        $this->upload($hr, $employee, ['type' => 'contract', 'title' => 'Contract 2026']);

        $this->actingAs($hr)
            ->get(route('admin.employees.documents.index', [$employee, 'type' => 'contract']))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.title', 'Contract 2026'));
    }

    // Access tab

    public function test_an_admin_can_change_access_from_the_profile()
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.employees.access.update', $employee), [
                'role_id' => Role::bySlug(Role::HR)->id,
                'overrides' => ['users.edit' => 'deny', 'settings.edit' => 'allow'],
            ])
            ->assertSessionHas('success');

        $permissions = $employee->user->fresh()->permissions();
        $this->assertNotContains('users.edit', $permissions);
        $this->assertContains('settings.edit', $permissions);
        $this->assertContains('employees.view', $permissions);
    }

    public function test_the_access_tab_is_for_role_managers_only()
    {
        $employee = Employee::factory()->create();
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.employees.access', $employee))->assertForbidden();
        $this->actingAs($hr)
            ->put(route('admin.employees.access.update', $employee), ['role_id' => Role::bySlug(Role::ADMIN)->id])
            ->assertForbidden();

        $this->assertFalse($employee->user->fresh()->isSuper());
    }
}
