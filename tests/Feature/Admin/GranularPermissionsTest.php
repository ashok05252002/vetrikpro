<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranularPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function withPermissions(array $keys): User
    {
        $role = Role::create(['name' => 'Custom '.uniqid(), 'slug' => 'custom-'.uniqid()]);
        $role->syncPermissions($keys);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_view_only_can_list_but_not_create_edit_or_delete()
    {
        $viewer = $this->withPermissions(['employees.view']);
        $target = Employee::factory()->create();

        $this->actingAs($viewer)->get(route('admin.employees.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.employees.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.employees.store'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.employees.edit', $target))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.employees.destroy', $target))->assertForbidden();

        $this->assertNotNull($target->fresh());
    }

    public function test_create_without_delete()
    {
        $clerk = $this->withPermissions(['departments.create']);
        $department = Department::factory()->create();

        $this->actingAs($clerk)->get(route('admin.departments.index'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.departments.store'), ['name' => 'Legal'])->assertRedirect();
        $this->actingAs($clerk)->get(route('admin.departments.edit', $department))->assertForbidden();
        $this->actingAs($clerk)->delete(route('admin.departments.destroy', $department))->assertForbidden();

        $this->assertDatabaseHas('departments', ['name' => 'Legal']);
        $this->assertNotNull($department->fresh());
    }

    public function test_any_action_implies_view()
    {
        $this->assertSame(['projects.view', 'projects.delete'], Permissions::only(['projects.delete']));
        // A module without "view" (merge_requests) implies nothing extra.
        $this->assertSame(['merge_requests.review'], Permissions::only(['merge_requests.review']));

        $editor = $this->withPermissions(['designations.edit']);
        $this->assertTrue($editor->can('designations.view'));
    }

    public function test_project_actions_are_separate()
    {
        $project = Project::factory()->create();
        $editor = $this->withPermissions(['projects.edit']);

        $this->actingAs($editor)->get(route('admin.projects.edit', $project))->assertOk();
        $this->actingAs($editor)->get(route('admin.projects.create'))->assertForbidden();
        $this->actingAs($editor)->delete(route('admin.projects.destroy', $project))->assertForbidden();
        // Editing any project includes working on it.
        $this->actingAs($editor)->get(route('projects.show', $project))->assertOk();
    }

    public function test_settings_view_does_not_allow_saving()
    {
        $reader = $this->withPermissions(['settings.view']);

        $this->actingAs($reader)->get(route('admin.settings.edit'))->assertOk();
        $this->actingAs($reader)->post(route('admin.settings.update'), ['company_name' => 'Hijacked'])->assertForbidden();
    }

    public function test_hr_kept_exactly_its_old_scope_through_the_split()
    {
        $hr = User::factory()->hr()->create();

        // Plus employees.promote, granted to HR on its own when promotions arrived.
        $this->assertSame([
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete', 'employees.onboard', 'employees.promote',
            'documents.view', 'documents.create', 'documents.delete',
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            'designations.view', 'designations.create', 'designations.edit', 'designations.delete',
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
        ], $hr->permissions());
    }

    public function test_the_owner_lookup_needs_create_or_edit_on_projects()
    {
        $this->actingAs($this->withPermissions(['projects.view']))->getJson(route('admin.lookups.users'))->assertForbidden();
        $this->actingAs($this->withPermissions(['projects.create']))->getJson(route('admin.lookups.users'))->assertOk();
    }

    public function test_the_editor_matrix_lists_every_permission_once()
    {
        $keys = collect(Permissions::forEditor())->flatMap(fn ($g) => collect($g['modules'])->flatMap(fn ($m) => collect($m['actions'])->pluck('key')));

        $this->assertEqualsCanonicalizing(Permissions::all(), $keys->all());
        $this->assertSame($keys->count(), $keys->unique()->count());
    }
}
