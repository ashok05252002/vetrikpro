<?php

namespace Tests\Feature\Work;

use App\Models\EmployeeDocument;
use App\Models\Project;
use App\Models\RequirementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequirementDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(EmployeeDocument::DISK);
    }

    private function file(string $name = 'spec.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    private function create(User $actor, Project $project, string $title = 'Leave spec')
    {
        return $this->actingAs($actor)->post(route('projects.requirements.store', $project), [
            'title' => $title,
            'file' => $this->file(),
        ]);
    }

    public function test_the_owner_adds_a_numbered_document_at_version_one()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->create($owner, $project)->assertSessionHas('success');
        $this->create($owner, $project, 'Attendance spec');

        $documents = $project->requirements()->orderBy('number')->get();
        $this->assertSame(['REQ-1', 'REQ-2'], $documents->map->reference()->all());
        $this->assertSame(1, $documents[0]->latestVersion->version);
        Storage::disk(EmployeeDocument::DISK)->assertExists($documents[0]->latestVersion->file_path);
    }

    public function test_a_new_version_keeps_the_old_one()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->create($owner, $project);
        $document = $project->requirements()->first();
        $first = $document->latestVersion;

        $this->actingAs($owner)
            ->post(route('projects.requirements.versions.store', [$project, $document]), ['file' => $this->file('spec-v2.pdf'), 'change_note' => 'Half-day leave added.'])
            ->assertSessionHas('success', 'REQ-1 is now at version 2.');

        $document->refresh();
        $this->assertSame(2, $document->latestVersion->version);
        $this->assertSame('Half-day leave added.', $document->latestVersion->change_note);
        $this->assertSame(2, $document->versions()->count());
        Storage::disk(EmployeeDocument::DISK)->assertExists($first->file_path);
    }

    public function test_a_new_version_must_say_what_changed()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->create($owner, $project);

        $this->actingAs($owner)
            ->post(route('projects.requirements.versions.store', [$project, $project->requirements()->first()]), ['file' => $this->file()])
            ->assertSessionHasErrors('change_note');
    }

    public function test_every_version_downloads_under_a_versioned_name()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->create($owner, $project);
        $document = $project->requirements()->first();
        $this->actingAs($owner)->post(route('projects.requirements.versions.store', [$project, $document]), ['file' => $this->file(), 'change_note' => 'v2']);

        $member = User::factory()->create();
        $project->members()->attach($member);
        $v1 = $document->versions()->where('version', 1)->first();

        $this->actingAs($member)
            ->get(route('projects.requirements.versions.download', [$project, $document, $v1]))
            ->assertOk()
            ->assertDownload('spec (v1).pdf');
    }

    public function test_members_read_requirements_but_only_definers_upload()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $devAdmin = User::factory()->create();
        $project->members()->attach($member);
        $project->members()->attach($devAdmin, ['role' => 'dev_admin']);

        $this->actingAs($member)->get(route('projects.requirements.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.create', false));
        $this->create($member, $project)->assertForbidden();

        // A dev admin defines the work too.
        $this->create($devAdmin, $project)->assertSessionHas('success');
    }

    public function test_outsiders_cannot_see_or_download()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->create($owner, $project);
        $document = $project->requirements()->first();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('projects.requirements.index', $project))->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('projects.requirements.versions.download', [$project, $document, $document->latestVersion]))
            ->assertForbidden();
    }

    public function test_a_version_is_only_reachable_through_its_own_document()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->create($owner, $project, 'One');
        $this->create($owner, $project, 'Two');
        [$one, $two] = $project->requirements()->orderBy('number')->get();

        $this->actingAs($owner)
            ->get(route('projects.requirements.versions.download', [$project, $one, $two->latestVersion]))
            ->assertNotFound();
    }

    public function test_deleting_a_document_removes_every_version_file()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->create($owner, $project);
        $document = $project->requirements()->first();
        $this->actingAs($owner)->post(route('projects.requirements.versions.store', [$project, $document]), ['file' => $this->file(), 'change_note' => 'v2']);
        $paths = $document->versions()->pluck('file_path');

        $this->actingAs($owner)->delete(route('projects.requirements.destroy', [$project, $document]))->assertRedirect(route('projects.requirements.index', $project));

        $this->assertNull(RequirementDocument::find($document->id));
        foreach ($paths as $path) {
            Storage::disk(EmployeeDocument::DISK)->assertMissing($path);
        }
    }

    public function test_the_list_searches_by_number()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->create($owner, $project, 'First');
        $this->create($owner, $project, 'Second');

        $this->actingAs($owner)
            ->get(route('projects.requirements.index', [$project, 'search' => 'REQ-2']))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.title', 'Second')->where('documents.data.0.current.version', 1));
    }
}
