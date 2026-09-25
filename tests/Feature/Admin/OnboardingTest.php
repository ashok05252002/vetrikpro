<?php

namespace Tests\Feature\Admin;

use App\Enums\OnboardingStatus;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\Onboarding\OnboardingChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(EmployeeDocument::DISK);
        Notification::fake();
    }

    private function hire(User $hr, array $overrides = [])
    {
        return $this->actingAs($hr)->post(route('admin.employees.store'), [
            'mode' => 'new',
            'name' => 'Priya Raman',
            'email' => 'priya@company.com',
            'send_invite' => true,
            'offer_letter' => UploadedFile::fake()->create('offer.pdf', 80, 'application/pdf'),
            'employee_code' => 'EMP-0100',
            'employment_type' => 'full_time',
            'status' => 'probation',
            ...$overrides,
        ]);
    }

    private function invitedLink(): string
    {
        $link = null;
        Notification::assertSentTo(User::where('email', 'priya@company.com')->first(), EmployeeInvitation::class, function (EmployeeInvitation $n) use (&$link) {
            $link = $n->url;

            return true;
        });

        return $link;
    }

    private function tokenFrom(string $link): string
    {
        return basename(parse_url($link, PHP_URL_PATH));
    }

    /** Fill everything the checklist asks for, as the employee. */
    private function complete(User $employee): void
    {
        $this->actingAs($employee)->put(route('onboarding.details'), ['phone' => '+91 98765 43210', 'date_of_birth' => '1996-02-10', 'address' => '12 Anna Salai, Chennai']);
        $this->actingAs($employee)->put(route('onboarding.bank'), [
            'account_name' => 'Priya Raman', 'account_number' => '001234567890', 'account_number_confirmation' => '001234567890',
            'ifsc' => 'sbin0001234', 'bank_name' => 'State Bank of India',
        ]);

        foreach (DocumentType::active()->where('is_required', true)->get() as $type) {
            $this->actingAs($employee)->post(route('onboarding.documents.store'), [
                'document_type_id' => $type->id, 'file' => UploadedFile::fake()->create("{$type->code}.pdf", 30, 'application/pdf'),
            ]);
        }
    }

    // HR creates and invites

    public function test_hr_creates_a_new_person_and_the_invite_goes_out_with_the_offer_letter()
    {
        $this->hire(User::factory()->hr()->create())->assertSessionHas('success');

        $user = User::where('email', 'priya@company.com')->firstOrFail();
        $employee = $user->employee;

        $this->assertSame('employee', $user->role->slug);
        $this->assertSame(OnboardingStatus::Invited, $employee->onboarding_status);
        $this->assertNotNull($employee->invited_at);
        $this->assertSame('offer.pdf', $employee->offer_letter_name);
        Storage::disk(EmployeeDocument::DISK)->assertExists($employee->offer_letter_path);

        Notification::assertSentTo($user, EmployeeInvitation::class, fn (EmployeeInvitation $n) => $n->offerLetterPath === $employee->offer_letter_path
            && str_contains($n->url, '/invitation/')
            && $n->expiresInHours === 72);
    }

    public function test_the_invite_link_is_shown_to_hr_while_mail_is_local()
    {
        config(['mail.default' => 'log']);

        $this->hire(User::factory()->hr()->create())->assertSessionHas('invite_link', fn ($link) => str_contains($link, '/invitation/'));
    }

    public function test_the_invite_can_wait_until_later()
    {
        $this->hire(User::factory()->hr()->create(), ['send_invite' => false]);

        Notification::assertNothingSent();
        $this->assertNull(User::where('email', 'priya@company.com')->first()->employee->invited_at);
    }

    public function test_a_new_persons_email_must_be_free()
    {
        User::factory()->create(['email' => 'priya@company.com']);

        $this->hire(User::factory()->hr()->create())->assertSessionHasErrors('email');
    }

    public function test_the_welcome_email_never_contains_a_password()
    {
        $this->hire(User::factory()->hr()->create());
        $user = User::where('email', 'priya@company.com')->first();

        Notification::assertSentTo($user, EmployeeInvitation::class, function (EmployeeInvitation $n) use ($user) {
            $body = implode(' ', $n->toMail($user)->introLines);

            return ! str_contains(strtolower($body), 'password:') && str_contains($body, $user->email);
        });
    }

    // Accepting the invite

    public function test_the_link_sets_a_password_signs_them_in_and_starts_onboarding()
    {
        $this->hire(User::factory()->hr()->create());
        $link = $this->invitedLink();
        auth()->logout();

        $this->get($link)->assertInertia(fn (Assert $page) => $page->component('auth/accept-invitation')->where('valid', true)->where('name', 'Priya Raman'));

        $this->post(route('invitation.store'), [
            'token' => $this->tokenFrom($link), 'email' => 'priya@company.com',
            'password' => 'Str0ng-Passw0rd', 'password_confirmation' => 'Str0ng-Passw0rd',
        ])->assertRedirect(route('onboarding.show'));

        $user = User::where('email', 'priya@company.com')->first();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(OnboardingStatus::InProgress, $user->employee->onboarding_status);
    }

    public function test_a_resent_invite_replaces_the_old_link()
    {
        $hr = User::factory()->hr()->create();
        $this->hire($hr);
        $first = $this->invitedLink();
        $employee = User::where('email', 'priya@company.com')->first()->employee;

        $this->actingAs($hr)->post(route('admin.employees.onboarding.invite', $employee))->assertSessionHas('success');
        auth()->logout();

        $this->get($first)->assertInertia(fn (Assert $page) => $page->where('valid', false));
        $this->post(route('invitation.store'), [
            'token' => $this->tokenFrom($first), 'email' => 'priya@company.com',
            'password' => 'Str0ng-Passw0rd', 'password_confirmation' => 'Str0ng-Passw0rd',
        ])->assertSessionHasErrors('email');
    }

    public function test_an_expired_link_is_refused()
    {
        $this->hire(User::factory()->hr()->create());
        $link = $this->invitedLink();
        auth()->logout();

        $this->travel(73)->hours();

        $this->get($link)->assertInertia(fn (Assert $page) => $page->where('valid', false));
    }

    // The employee's checklist

    public function test_until_they_submit_the_portal_is_the_onboarding_page()
    {
        $this->hire(User::factory()->hr()->create());
        $user = User::where('email', 'priya@company.com')->first();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
        $this->actingAs($user)->get(route('projects.index'))->assertRedirect(route('onboarding.show'));
        $this->actingAs($user)->get(route('onboarding.show'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('onboarding/show'));
    }

    public function test_people_who_joined_before_onboarding_are_not_affected()
    {
        $existing = Employee::factory()->create();

        $this->actingAs($existing->user)->get(route('dashboard'))->assertOk();
    }

    public function test_the_checklist_needs_required_documents_bank_and_the_signed_offer_letter()
    {
        $this->hire(User::factory()->hr()->create());
        $employee = User::where('email', 'priya@company.com')->first()->employee;

        $labels = collect(OnboardingChecklist::for($employee))->where('required', true)->pluck('label')->all();

        $this->assertSame(['Personal details', 'Bank details', 'Aadhaar card', 'PAN card', 'Signed offer letter'], $labels);
    }

    public function test_without_an_offer_letter_no_signed_copy_is_asked_for()
    {
        $this->hire(User::factory()->hr()->create(), ['offer_letter' => null]);
        $employee = User::where('email', 'priya@company.com')->first()->employee;

        $this->assertNotContains('Signed offer letter', collect(OnboardingChecklist::for($employee))->pluck('label'));
    }

    public function test_bank_details_are_validated_and_the_account_number_is_encrypted()
    {
        $this->hire(User::factory()->hr()->create());
        $user = User::where('email', 'priya@company.com')->first();

        $this->actingAs($user)->put(route('onboarding.bank'), [
            'account_name' => 'Priya', 'account_number' => '1234567890', 'account_number_confirmation' => '1234567899', 'ifsc' => 'NOTANIFSC', 'bank_name' => 'SBI',
        ])->assertSessionHasErrors(['account_number', 'ifsc']);

        $this->complete($user);
        $employee = $user->employee->fresh();

        $this->assertSame('SBIN0001234', $employee->bank_ifsc);
        $this->assertSame('001234567890', $employee->bank_account_number);
        $this->assertNotSame('001234567890', \DB::table('employees')->where('id', $employee->id)->value('bank_account_number'));
        $this->assertSame('••••••••7890', $employee->maskedAccountNumber());
    }

    public function test_uploading_again_replaces_the_file_in_that_slot()
    {
        $this->hire(User::factory()->hr()->create());
        $user = User::where('email', 'priya@company.com')->first();
        $pan = DocumentType::where('code', 'pan')->first();

        $this->actingAs($user)->post(route('onboarding.documents.store'), ['document_type_id' => $pan->id, 'file' => UploadedFile::fake()->create('old.pdf', 10, 'application/pdf')]);
        $old = $user->employee->documents()->first()->file_path;
        $this->actingAs($user)->post(route('onboarding.documents.store'), ['document_type_id' => $pan->id, 'file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')]);

        $this->assertSame(['new.pdf'], $user->employee->documents()->pluck('original_name')->all());
        Storage::disk(EmployeeDocument::DISK)->assertMissing($old);
    }

    public function test_submitting_early_is_refused_and_complete_is_accepted()
    {
        $this->hire(User::factory()->hr()->create());
        $user = User::where('email', 'priya@company.com')->first();

        $this->actingAs($user)->post(route('onboarding.submit'))->assertSessionHas('error');

        $this->complete($user);
        $signed = DocumentType::signedOfferLetter();
        $this->actingAs($user)->post(route('onboarding.documents.store'), ['document_type_id' => $signed->id, 'file' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf')]);

        $this->actingAs($user)->post(route('onboarding.submit'))->assertRedirect(route('dashboard'));

        $this->assertSame(OnboardingStatus::Submitted, $user->employee->fresh()->onboarding_status);
        // Now the portal opens up.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        // And their answers are frozen until HR sends it back.
        $this->actingAs($user)->put(route('onboarding.details'), ['phone' => '000', 'date_of_birth' => '1990-01-01', 'address' => 'x'])->assertForbidden();
    }

    public function test_an_employee_only_ever_reaches_their_own_files()
    {
        $this->hire(User::factory()->hr()->create());
        $user = User::where('email', 'priya@company.com')->first();
        $someone = Employee::factory()->create();
        $theirs = $someone->documents()->create([
            'document_type_id' => DocumentType::where('code', 'pan')->value('id'), 'title' => 'PAN', 'file_path' => 'x', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
        ]);

        $this->actingAs($user)->get(route('onboarding.documents.download', $theirs))->assertNotFound();
        $this->actingAs($user)->delete(route('onboarding.documents.destroy', $theirs))->assertNotFound();
        $this->assertNotNull($theirs->fresh());
    }

    // HR review

    public function test_hr_sends_it_back_and_then_approves()
    {
        $hr = User::factory()->hr()->create();
        $this->hire($hr);
        $user = User::where('email', 'priya@company.com')->first();
        $this->complete($user);
        $this->actingAs($user)->post(route('onboarding.documents.store'), ['document_type_id' => DocumentType::signedOfferLetter()->id, 'file' => UploadedFile::fake()->create('s.pdf', 10, 'application/pdf')]);
        $this->actingAs($user)->post(route('onboarding.submit'));
        $employee = $user->employee;

        $this->actingAs($hr)->get(route('admin.employees.onboarding', $employee))
            ->assertInertia(fn (Assert $page) => $page->where('onboarding.status', 'submitted')->where('onboarding.progress.complete', true)
                // HR sees the account number masked.
                ->where('onboarding.bank.account_number', '••••••••7890'));

        $this->actingAs($hr)->post(route('admin.employees.onboarding.send-back', $employee), ['note' => 'PAN is blurred.'])->assertSessionHas('success');
        $this->assertSame(OnboardingStatus::Returned, $employee->fresh()->onboarding_status);
        // A fresh instance, as every real request loads one; the old object still holds "submitted".
        $user = $user->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));

        $this->actingAs($user)->post(route('onboarding.submit'));
        $this->actingAs($hr)->post(route('admin.employees.onboarding.approve', $employee))->assertSessionHas('success');

        $employee->refresh();
        $this->assertSame(OnboardingStatus::Completed, $employee->onboarding_status);
        $this->assertSame($hr->id, $employee->onboarding_reviewed_by);
        $this->assertNull($employee->onboarding_note);
    }

    public function test_only_a_submitted_profile_can_be_approved()
    {
        $hr = User::factory()->hr()->create();
        $this->hire($hr);
        $employee = User::where('email', 'priya@company.com')->first()->employee;

        $this->actingAs($hr)->post(route('admin.employees.onboarding.approve', $employee))->assertSessionHas('error');
        $this->assertSame(OnboardingStatus::Invited, $employee->fresh()->onboarding_status);
    }

    public function test_reviewing_needs_the_onboard_permission()
    {
        $hr = User::factory()->hr()->create();
        $this->hire($hr);
        $employee = User::where('email', 'priya@company.com')->first()->employee;
        $viewer = User::factory()->create();
        $viewer->syncPermissionOverrides(['employees.view' => true]);

        $this->actingAs($viewer)->get(route('admin.employees.onboarding', $employee))->assertOk();
        $this->actingAs($viewer)->post(route('admin.employees.onboarding.invite', $employee))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.employees.onboarding.approve', $employee))->assertForbidden();
    }

    public function test_the_employee_list_shows_onboarding_progress()
    {
        $hr = User::factory()->hr()->create();
        $this->hire($hr);

        $this->actingAs($hr)->get(route('admin.employees.index', ['onboarding' => 'invited']))
            ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1)
                ->where('employees.data.0.onboarding.status', 'invited')
                ->where('employees.data.0.onboarding.done', 0)
                ->where('employees.data.0.onboarding.total', 5));
    }
}
