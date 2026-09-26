<?php

namespace Tests\Feature\Work;

use App\Models\EmployeeDocument;
use App\Models\Project;
use App\Models\TestPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestPointAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $member;

    private TestPoint $point;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(EmployeeDocument::DISK);

        $this->project = Project::factory()->create();
        $this->member = User::factory()->create();
        $this->project->members()->attach($this->member);
        $this->point = TestPoint::factory()->create(['project_id' => $this->project->id]);
    }

    private function upload(array $images, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->member)->post(route('testing.points.attachments.store', [$this->project, $this->point]), ['images' => $images]);
    }

    public function test_images_can_be_attached_and_viewed()
    {
        $this->upload([UploadedFile::fake()->image('login.png', 800, 600), UploadedFile::fake()->image('error.jpg')])->assertSessionHas('success', '2 images attached.');

        $attachment = $this->point->attachments()->first();
        Storage::disk(EmployeeDocument::DISK)->assertExists($attachment->file_path);

        $this->actingAs($this->member)
            ->get(route('testing.points.attachments.show', [$this->project, $this->point, $attachment]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_videos_documents_and_svg_are_refused()
    {
        $this->upload([UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4')])->assertSessionHasErrors('images.0');
        $this->upload([UploadedFile::fake()->create('report.pdf', 50, 'application/pdf')])->assertSessionHasErrors('images.0');
        $this->upload([UploadedFile::fake()->create('icon.svg', 5, 'image/svg+xml')])->assertSessionHasErrors('images.0');

        $this->assertSame(0, $this->point->attachments()->count());
    }

    public function test_images_over_2_mb_are_refused()
    {
        $this->upload([UploadedFile::fake()->image('huge.png')->size(2049)])
            ->assertSessionHasErrors(['images.0' => 'Each image must be 2 MB or smaller.']);

        $this->upload([UploadedFile::fake()->image('ok.png')->size(2048)])->assertSessionHasNoErrors();
    }

    public function test_outsiders_can_neither_attach_nor_see()
    {
        $this->upload([UploadedFile::fake()->image('a.png')]);
        $attachment = $this->point->attachments()->first();
        $outsider = User::factory()->create();

        $this->upload([UploadedFile::fake()->image('b.png')], $outsider)->assertForbidden();
        $this->actingAs($outsider)->get(route('testing.points.attachments.show', [$this->project, $this->point, $attachment]))->assertForbidden();
    }

    public function test_an_image_is_only_reachable_through_its_own_testing_point()
    {
        $this->upload([UploadedFile::fake()->image('a.png')]);
        $attachment = $this->point->attachments()->first();
        $other = TestPoint::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->member)->get(route('testing.points.attachments.show', [$this->project, $other, $attachment]))->assertNotFound();
    }

    public function test_only_the_uploader_or_a_project_editor_removes_an_image()
    {
        $this->upload([UploadedFile::fake()->image('a.png')]);
        $attachment = $this->point->attachments()->first();
        $colleague = User::factory()->create();
        $this->project->members()->attach($colleague);

        $this->actingAs($colleague)->delete(route('testing.points.attachments.destroy', [$this->project, $this->point, $attachment]))->assertForbidden();
        $this->actingAs($this->member)->delete(route('testing.points.attachments.destroy', [$this->project, $this->point, $attachment]))->assertRedirect();

        $this->assertNull($attachment->fresh());
        Storage::disk(EmployeeDocument::DISK)->assertMissing($attachment->file_path);
    }

    public function test_deleting_the_testing_point_removes_its_images()
    {
        $this->upload([UploadedFile::fake()->image('a.png')]);
        $path = $this->point->attachments()->first()->file_path;

        $this->point->delete();

        Storage::disk(EmployeeDocument::DISK)->assertMissing($path);
    }
}
