<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\OfferLetter;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfferLetterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(EmployeeDocument::DISK);
        Storage::fake('uploads');
        Notification::fake();
        app(Settings::class)->set(['company.name' => 'Vetrik Private Limited', 'display.currency' => 'INR', 'display.date_format' => 'dmy']);
    }

    private function hire(array $overrides = [])
    {
        $designation = Designation::factory()->create(['name' => 'Software Engineer']);

        return $this->actingAs(User::factory()->admin()->create())->post(route('admin.employees.store'), [
            'name' => 'Priya Raman',
            'email' => 'priya@company.com',
            'employee_code' => 'EMP-0042',
            'designation_id' => $designation->id,
            'date_of_joining' => '2026-10-16',
            'salary' => '65000',
            'employment_type' => 'full_time',
            'status' => 'probation',
            'offer_letter_mode' => 'generate',
            'send_invite' => true,
            ...$overrides,
        ]);
    }

    public function test_the_letter_fills_every_placeholder_from_the_employee()
    {
        $this->hire();
        $employee = User::where('email', 'priya@company.com')->first()->employee;
        $html = app(OfferLetter::class)->html(app(OfferLetter::class)->valuesFor($employee));

        $this->assertStringContainsString('Dear Priya Raman,', $html);
        $this->assertStringContainsString('<strong>Software Engineer</strong>', $html);
        $this->assertStringContainsString('16 October 2026', $html);
        // Indian digit grouping, and "Rs." because the PDF fonts have no rupee sign.
        $this->assertStringContainsString('Rs. 65,000', $html);
        $this->assertStringContainsString('Rs. 7,80,000', $html);
        $this->assertStringContainsString('OL/EMP-0042/', $html);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $html);
    }

    public function test_names_and_template_text_cannot_inject_html()
    {
        app(Settings::class)->set(['offer.body' => "Dear {employee_name},\n\n<b>raw</b> and **bold**"]);
        $employee = Employee::factory()->create();
        $employee->user->update(['name' => '<script>alert(1)</script>']);

        $html = app(OfferLetter::class)->html(app(OfferLetter::class)->valuesFor($employee->fresh()));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>raw</b>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_creating_with_generate_stores_a_pdf_and_attaches_it_to_the_invite()
    {
        $this->hire()->assertSessionHasNoErrors();

        $user = User::where('email', 'priya@company.com')->first();
        $employee = $user->employee;

        $this->assertSame('Offer letter - Priya Raman.pdf', $employee->offer_letter_name);
        $this->assertStringStartsWith('%PDF', Storage::disk(EmployeeDocument::DISK)->get($employee->offer_letter_path));
        Notification::assertSentTo($user, EmployeeInvitation::class, fn (EmployeeInvitation $n) => $n->offerLetterPath === $employee->offer_letter_path);
    }

    public function test_a_generated_letter_needs_a_joining_date_and_salary()
    {
        $this->hire(['date_of_joining' => '', 'salary' => ''])->assertSessionHasErrors(['date_of_joining', 'salary']);
        $this->hire(['date_of_joining' => '', 'salary' => '', 'offer_letter_mode' => 'none', 'email' => 'other@company.com'])->assertSessionHasNoErrors();
    }

    public function test_no_offer_letter_means_none_is_asked_for()
    {
        $this->hire(['offer_letter_mode' => 'none']);

        $this->assertNull(User::where('email', 'priya@company.com')->first()->employee->offer_letter_path);
    }

    public function test_hr_can_regenerate_after_correcting_details()
    {
        $this->hire();
        $employee = User::where('email', 'priya@company.com')->first()->employee;
        $first = $employee->offer_letter_path;
        $employee->update(['salary' => 70000]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.employees.onboarding.offer-letter.generate', $employee))
            ->assertSessionHas('success');

        $employee->refresh();
        $this->assertNotSame($first, $employee->offer_letter_path);
        Storage::disk(EmployeeDocument::DISK)->assertMissing($first);
        $this->assertStringContainsString('Rs. 70,000', app(OfferLetter::class)->html(app(OfferLetter::class)->valuesFor($employee)));
    }

    // The welcome letter: no salary, for contract staff

    public function test_a_welcome_letter_needs_no_salary_and_states_none()
    {
        $this->hire(['offer_letter_mode' => 'welcome', 'employment_type' => 'contract', 'salary' => ''])->assertSessionHasNoErrors();

        $user = User::where('email', 'priya@company.com')->first();
        $employee = $user->employee;

        $this->assertNull($employee->salary);
        $this->assertSame('welcome', $employee->offer_letter_kind);
        $this->assertSame('Welcome letter - Priya Raman.pdf', $employee->offer_letter_name);
        $this->assertStringStartsWith('%PDF', Storage::disk(EmployeeDocument::DISK)->get($employee->offer_letter_path));
        Notification::assertSentTo($user, EmployeeInvitation::class, fn (EmployeeInvitation $n) => $n->offerLetterPath === $employee->offer_letter_path
            && $n->offerLetterKind === 'welcome'
            && str_contains($n->toMail($user)->render(), 'Your welcome letter is attached'));
    }

    public function test_a_welcome_letter_leaves_out_the_package_even_when_a_salary_is_known()
    {
        // Even a template that asks for the salary does not get it.
        app(Settings::class)->set(['welcome.body' => 'Hello {first_name}, you earn {monthly_salary}.']);
        $this->hire(['offer_letter_mode' => 'welcome']);
        $employee = User::where('email', 'priya@company.com')->first()->employee;
        $letter = app(OfferLetter::class);

        $html = $letter->html($letter->valuesFor($employee), null, OfferLetter::WELCOME);

        $this->assertStringContainsString('Welcome Letter', $html);
        $this->assertStringContainsString('you earn as discussed', $html);
        $this->assertStringNotContainsString('65,000', $html);
        $this->assertStringNotContainsString('Annual CTC', $html);
        $this->assertStringNotContainsString('Monthly salary', $html);
        $this->assertStringContainsString('WL/EMP-0042/', $html);
    }

    public function test_a_welcome_letter_still_needs_a_joining_date()
    {
        $this->hire(['offer_letter_mode' => 'welcome', 'salary' => '', 'date_of_joining' => ''])
            ->assertSessionHasErrors('date_of_joining')
            ->assertSessionDoesntHaveErrors('salary');
    }

    public function test_hr_can_switch_between_the_offer_and_welcome_letters()
    {
        $this->hire(['offer_letter_mode' => 'welcome', 'salary' => '']);
        $employee = User::where('email', 'priya@company.com')->first()->employee;
        $admin = User::factory()->admin()->create();

        // No salary on record: an offer letter would have nothing to state.
        $this->actingAs($admin)
            ->post(route('admin.employees.onboarding.offer-letter.generate', $employee), ['kind' => 'offer'])
            ->assertSessionHas('error');
        $this->assertSame('welcome', $employee->fresh()->offer_letter_kind);

        $employee->update(['salary' => 50000]);
        $this->actingAs($admin)
            ->post(route('admin.employees.onboarding.offer-letter.generate', $employee), ['kind' => 'offer'])
            ->assertSessionHas('success');
        $this->assertSame('offer', $employee->fresh()->offer_letter_kind);
        $this->assertSame('Offer letter - Priya Raman.pdf', $employee->fresh()->offer_letter_name);

        $this->actingAs($admin)
            ->post(route('admin.employees.onboarding.offer-letter.generate', $employee), ['kind' => 'nonsense'])
            ->assertSessionHasErrors('kind');
    }

    // The template in the Configuration hub

    public function test_the_template_is_saved_and_used()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.config.offer-letter.update'), [
                'title' => 'Letter of Offer',
                'body' => "Hi {first_name},\n\nWelcome aboard as {designation}.",
                'signatory_name' => 'Ashok Kumar',
                'signatory_title' => 'Director',
                'valid_days' => 10,
                'welcome_title' => 'Welcome Aboard',
                'welcome_body' => 'Greetings {first_name}.',
            ])
            ->assertSessionHas('success');

        $html = app(OfferLetter::class)->html(app(OfferLetter::class)->sampleValues());

        $this->assertStringContainsString('Letter of Offer', $html);
        $this->assertStringContainsString('Hi Priya,', $html);
        $this->assertStringContainsString('Ashok Kumar', $html);
        $this->assertStringContainsString(today()->addDays(10)->format('j F Y'), $html);

        $welcome = app(OfferLetter::class)->html(app(OfferLetter::class)->sampleValues(), null, OfferLetter::WELCOME);
        $this->assertStringContainsString('Welcome Aboard', $welcome);
        $this->assertStringContainsString('Greetings Priya.', $welcome);
        $this->assertStringContainsString('Ashok Kumar', $welcome);
    }

    public function test_an_unknown_placeholder_is_refused()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.config.offer-letter.update'), [
                'title' => 'Offer', 'body' => 'Joining on {joinig_date}', 'valid_days' => 7,
                'welcome_title' => 'Welcome', 'welcome_body' => 'Hi {frist_name}',
            ])
            ->assertSessionHasErrors([
                'body' => 'Unknown placeholder: {joinig_date}. Pick one from the list.',
                'welcome_body' => 'Unknown placeholder: {frist_name}. Pick one from the list.',
            ]);
    }

    public function test_the_preview_is_a_pdf_and_needs_settings_access()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.config.offer-letter.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('admin.config.offer-letter.preview', ['kind' => 'welcome']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="welcome-letter-preview.pdf"');

        $this->actingAs(User::factory()->hr()->create())->get(route('admin.config.offer-letter.preview'))->assertForbidden();

        $reader = User::factory()->create();
        $reader->syncPermissionOverrides(['settings.view' => true]);
        $this->actingAs($reader)->get(route('admin.config.offer-letter.edit'))->assertOk();
        $this->actingAs($reader)->put(route('admin.config.offer-letter.update'), ['title' => 'x', 'body' => 'y', 'valid_days' => 7])->assertForbidden();
    }

    // The hub

    public function test_the_hub_lists_areas_and_what_is_missing()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.config.hub'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/config/hub')
                ->where('areas.organisation.missing', fn ($missing) => collect($missing)->contains('Address') && collect($missing)->contains('Logo'))
                ->where('areas.documents.required', 3)
                ->where('areas.offer.valid_days', 7));
    }

    public function test_the_hub_shows_only_the_areas_you_may_open()
    {
        $docsOnly = User::factory()->create();
        $docsOnly->syncPermissionOverrides(['document_types.view' => true]);

        $this->actingAs($docsOnly)->get(route('admin.config.hub'))
            ->assertInertia(fn (Assert $page) => $page->where('areas.organisation', null)->where('areas.offer', null)->whereNot('areas.documents', null));

        $this->actingAs(User::factory()->create())->get(route('admin.config.hub'))->assertForbidden();
    }
}
