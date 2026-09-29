<?php

namespace Tests\Feature\Work;

use App\Models\Project;
use App\Models\RequirementVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RequirementPointsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lead = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->lead->id]);
    }

    /** @param list<list<mixed>> $rows */
    private function workbook(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'req').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'requirements.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function good(): UploadedFile
    {
        return $this->workbook([
            ['Requirement S.No', 'Module', 'Description', 'Additional Notes'],
            [1, 'Login', 'Users sign in with email and password', null],
            [2, 'Login', 'Lock the account after 5 failed attempts', 'Unlock by email'],
            [],
            [5, 'Payroll', 'Export the monthly payroll as CSV', null],
        ]);
    }

    public function test_versions_run_in_strict_sequence()
    {
        $label = fn () => RequirementVersion::nextFor($this->project)['label'];
        $add = fn (int $major, int $minor) => RequirementVersion::create(['project_id' => $this->project->id, 'major' => $major, 'minor' => $minor, 'source' => 'manual']);

        $this->assertSame('V1', $label());
        $add(1, 0);
        $this->assertSame('V1.1', $label());
        $add(1, 9);
        $this->assertSame('V1.10', $label());
        $add(1, 10);
        $this->assertSame('V2', $label());
    }

    public function test_the_template_downloads_with_the_exact_headers()
    {
        $response = $this->actingAs($this->lead)->get(route('projects.requirements.template', $this->project));

        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->getContent());
        $header = IOFactory::load($path)->getSheet(0)->rangeToArray('A1:D1')[0];

        $this->assertSame(['Requirement S.No', 'Module', 'Description', 'Additional Notes'], $header);
    }

    public function test_a_clean_file_checks_clean_and_imports_as_the_next_version()
    {
        $this->actingAs($this->lead)->post(route('projects.requirements.check', $this->project), ['file' => $this->good()])
            ->assertOk()
            ->assertJsonPath('header_errors', [])
            ->assertJsonPath('errors', [])
            ->assertJsonCount(3, 'points');

        $this->actingAs($this->lead)->post(route('projects.requirements.import', $this->project), ['file' => $this->good()])->assertSessionHas('success');

        $version = RequirementVersion::sole();
        $this->assertSame('V1', $version->label());
        $this->assertSame('excel', $version->source);
        $this->assertSame([1, 2, 5], $version->points()->pluck('number')->all());
    }

    public function test_header_mistakes_are_reported_and_block_the_import()
    {
        $bad = fn () => $this->workbook([['S No', 'Module', 'Details'], [1, 'Login', 'x']]);

        $this->actingAs($this->lead)->post(route('projects.requirements.check', $this->project), ['file' => $bad()])
            ->assertJsonCount(3, 'header_errors') // A wrong, C wrong, D missing
            ->assertJsonPath('header_errors.0', 'Column A header is “S No” — it should be “Requirement S.No”.');

        $this->actingAs($this->lead)->post(route('projects.requirements.import', $this->project), ['file' => $bad()])->assertSessionHasErrors('file');
        $this->assertSame(0, RequirementVersion::count());
    }

    public function test_row_mistakes_are_reported_with_their_row_and_column()
    {
        $file = $this->workbook([
            ['Requirement S.No', 'Module', 'Description', 'Additional Notes'],
            [1, 'Login', 'Fine', null],
            [1, 'Login', 'Duplicate number', null],
            ['two', '', 'Bad number, no module', null],
        ]);

        $errors = $this->actingAs($this->lead)->post(route('projects.requirements.check', $this->project), ['file' => $file])->json('errors');

        $this->assertContains(['row' => 3, 'column' => 'Requirement S.No', 'message' => '1 is already used on row 2.'], $errors);
        $this->assertContains(['row' => 4, 'column' => 'Requirement S.No', 'message' => '“two” is not a whole number from 1 up.'], $errors);
        $this->assertContains(['row' => 4, 'column' => 'Module', 'message' => 'Missing.'], $errors);
    }

    public function test_manual_entry_numbers_points_in_order_and_takes_the_next_version()
    {
        RequirementVersion::create(['project_id' => $this->project->id, 'major' => 1, 'minor' => 0, 'source' => 'manual']);

        $this->actingAs($this->lead)->post(route('projects.requirements.manual.store', $this->project), ['points' => [
            ['module' => 'Login', 'description' => 'Sign in', 'notes' => null],
            ['module' => 'Reports', 'description' => 'Monthly report', 'notes' => 'PDF'],
        ]])->assertSessionHas('success');

        $version = RequirementVersion::latestFor($this->project);
        $this->assertSame('V1.1', $version->label());
        $this->assertSame([1, 2], $version->points()->pluck('number')->all());
    }

    public function test_only_the_projects_owner_leads_and_editors_add_versions_but_members_can_read()
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->get(route('projects.requirements.upload', $this->project))->assertForbidden();
        $this->actingAs($member)->post(route('projects.requirements.manual.store', $this->project), ['points' => [['module' => 'X', 'description' => 'Y']]])->assertForbidden();
        $this->actingAs($member)->get(route('projects.requirements.index', $this->project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('projects/requirements')->where('can.manage', false));
    }
}
