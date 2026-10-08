<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\OfferLetter;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Salaries and stipends are seen and set only with employees.salary and
 * interns.stipend, ticked on the role — nothing else brings them. Everyone
 * else gets the record, and the documents, without the amounts.
 */
class PayAccessTest extends TestCase
{
    use RefreshDatabase;

    private function withPermissions(array $keys): User
    {
        $role = Role::create(['name' => 'Custom '.uniqid(), 'slug' => 'custom-'.uniqid()]);
        $role->syncPermissions($keys);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function intern(array $attributes = []): Employee
    {
        return Employee::factory()->create(['employment_type' => Employee::INTERN, 'has_stipend' => true, 'stipend' => 12000, ...$attributes]);
    }

    public function test_the_salary_is_hidden_from_a_viewer_without_the_permission()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);

        $this->actingAs($this->withPermissions(['employees.view']))
            ->get(route('admin.employees.show', $employee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canSeePay', false)
                ->missing('employee.salary'));

        $this->actingAs($this->withPermissions(['employees.view']))
            ->get(route('admin.employees.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('employees.data.0.salary'));
    }

    public function test_the_salary_is_shown_with_the_permission_and_to_hr()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);

        foreach ([$this->withPermissions(['employees.view', 'employees.salary']), User::factory()->hr()->create()] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('admin.employees.show', $employee))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('canSeePay', true)
                    ->where('employee.salary', '50000.00'));
        }
    }

    public function test_an_edit_without_the_permission_leaves_the_salary_alone()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);

        $this->actingAs($this->withPermissions(['employees.edit']))
            ->put(route('admin.employees.update', $employee), [
                'name' => 'Renamed', 'email' => $employee->user->email, 'employee_code' => $employee->employee_code,
                'employment_type' => 'full_time', 'status' => 'active', 'salary' => 99999,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(50000, $employee->fresh()->salary);
        $this->assertSame('Renamed', $employee->user->fresh()->name);
    }

    public function test_the_offer_letter_with_salary_needs_the_permission()
    {
        $this->actingAs($this->withPermissions(['employees.create']))
            ->post(route('admin.employees.store'), [
                'name' => 'New Person', 'email' => 'new@company.test', 'employee_code' => 'EMP-9001', 'employment_type' => 'full_time',
                'status' => 'active', 'date_of_joining' => '2026-11-02', 'offer_letter_mode' => 'generate', 'salary' => 40000,
            ])
            ->assertSessionHasErrors('offer_letter_mode');
    }

    public function test_promoting_does_not_include_seeing_salaries()
    {
        // Only what is ticked on the role counts: promote brings its module's view, nothing more.
        $this->assertNotContains('employees.salary', Permissions::only(['employees.promote']));
    }

    public function test_a_role_that_promotes_without_the_salary_permission_sees_no_pay()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);

        $this->actingAs($this->withPermissions(['employees.view', 'employees.promote']))
            ->get(route('admin.employees.show', $employee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('employee.salary')
                ->where('canSeePay', false)
                ->where('promoteOptions.canSetPay', false));
    }

    public function test_promoting_without_the_salary_permission_changes_the_designation_only()
    {
        Storage::fake(EmployeeDocument::DISK);
        Notification::fake();
        $employee = Employee::factory()->create(['salary' => 50000]);
        $senior = Designation::factory()->create();
        $promoter = $this->withPermissions(['employees.view', 'employees.promote']);

        $this->actingAs($promoter)->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $senior->id, 'to_salary' => 90000, 'effective_date' => '2026-10-01', 'send_email' => false,
        ])->assertSessionHasErrors('to_salary');

        $this->actingAs($promoter)->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $employee->designation_id, 'effective_date' => '2026-10-01', 'send_email' => false,
        ])->assertSessionHasErrors('to_designation_id');

        $this->actingAs($promoter)->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $senior->id, 'effective_date' => '2026-10-01', 'send_email' => false,
        ])->assertSessionHas('success');

        $employee->refresh();
        $this->assertSame($senior->id, $employee->designation_id);
        $this->assertEquals(50000, $employee->salary);
        $this->assertEquals(50000, Promotion::sole()->to_salary);
    }

    public function test_promoting_someone_with_no_salary_recorded_keeps_it_empty()
    {
        Storage::fake(EmployeeDocument::DISK);
        $employee = Employee::factory()->create(['salary' => null]);

        $this->actingAs($this->withPermissions(['employees.view', 'employees.promote']))
            ->post(route('admin.employees.promotions.store', $employee), [
                'to_designation_id' => Designation::factory()->create()->id, 'effective_date' => '2026-10-01', 'send_email' => false,
            ])->assertSessionHas('success');

        $this->assertNull(Promotion::sole()->to_salary);
        $this->assertNull(Promotion::sole()->incrementPercent());
    }

    public function test_documents_that_state_pay_need_the_pay_permission()
    {
        Storage::fake(EmployeeDocument::DISK);
        $employee = Employee::factory()->create();
        $offer = $this->document($employee, 'offer_letter');
        $pan = $this->document($employee, 'pan');

        $withoutPay = $this->withPermissions(['employees.view', 'documents.view', 'documents.delete']);
        $this->actingAs($withoutPay)
            ->get(route('admin.employees.documents.index', $employee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('documents.data', fn ($docs) => collect($docs)->pluck('id')->all() === [$pan->id])
                ->where('employee.counts.documents', 1));
        $this->actingAs($withoutPay)->get(route('admin.employees.documents.download', [$employee, $offer]))->assertForbidden();
        $this->actingAs($withoutPay)->get(route('admin.employees.documents.download', [$employee, $pan]))->assertOk();
        $this->actingAs($withoutPay)->delete(route('admin.employees.documents.destroy', [$employee, $offer]))->assertForbidden();

        $withPay = $this->withPermissions(['employees.view', 'documents.view', 'employees.salary']);
        $this->actingAs($withPay)
            ->get(route('admin.employees.documents.index', $employee))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('documents.data', 2));
        $this->actingAs($withPay)->get(route('admin.employees.documents.download', [$employee, $offer]))->assertOk();
    }

    public function test_an_interns_pay_documents_follow_the_stipend_permission()
    {
        Storage::fake(EmployeeDocument::DISK);
        $intern = $this->intern();
        $offer = $this->document($intern, 'offer_letter');

        $this->actingAs($this->withPermissions(['documents.view', 'employees.salary']))
            ->get(route('admin.employees.documents.download', [$intern, $offer]))->assertForbidden();
        $this->actingAs($this->withPermissions(['documents.view', 'interns.stipend']))
            ->get(route('admin.employees.documents.download', [$intern, $offer]))->assertOk();
    }

    public function test_only_a_role_that_sees_pay_decides_which_documents_state_it()
    {
        $offer = DocumentType::where('code', 'offer_letter')->sole();
        $this->assertTrue($offer->states_pay);

        $this->actingAs($this->withPermissions(['document_types.edit']))
            ->put(route('admin.config.document-types.update', $offer), ['name' => $offer->name, 'is_required' => false, 'is_active' => true, 'states_pay' => false])
            ->assertSessionHasErrors('states_pay');
        $this->assertTrue($offer->fresh()->states_pay);

        $this->actingAs($this->withPermissions(['document_types.edit', 'employees.salary']))
            ->put(route('admin.config.document-types.update', $offer), ['name' => $offer->name, 'is_required' => false, 'is_active' => true, 'states_pay' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($offer->fresh()->states_pay);
    }

    public function test_pay_never_leaves_the_server_unless_asked_for()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);

        $this->assertArrayNotHasKey('salary', $employee->toArray());
        $this->assertArrayHasKey('salary', $employee->withPayFor(User::factory()->admin()->create())->toArray());
    }

    private function document(Employee $employee, string $code): EmployeeDocument
    {
        Storage::disk(EmployeeDocument::DISK)->put("docs/{$code}.pdf", 'pdf');

        return $employee->documents()->create([
            'document_type_id' => DocumentType::where('code', $code)->value('id'),
            'title' => $code, 'file_path' => "docs/{$code}.pdf", 'original_name' => "{$code}.pdf", 'mime_type' => 'application/pdf', 'size' => 3,
        ]);
    }

    public function test_promotion_amounts_and_letters_need_the_permission()
    {
        Storage::fake(EmployeeDocument::DISK);
        $employee = Employee::factory()->create(['salary' => 50000]);
        $promotion = Promotion::create([
            'employee_id' => $employee->id, 'to_designation_name' => 'Senior', 'from_salary' => 50000, 'to_salary' => 57500,
            'effective_date' => '2026-10-01',
        ]);
        $promotion->forceFill(['letter_path' => 'letters/p.pdf'])->save();
        Storage::disk(EmployeeDocument::DISK)->put('letters/p.pdf', 'pdf');
        $viewer = $this->withPermissions(['employees.view']);

        $this->actingAs($viewer)
            ->get(route('admin.employees.show', $employee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('promotions.0.to_salary', null)
                ->where('promotions.0.letter_url', null));

        $this->actingAs($viewer)->get(route('admin.employees.promotions.letter', [$employee, $promotion]))->assertForbidden();
        $this->actingAs(User::factory()->hr()->create())->get(route('admin.employees.promotions.letter', [$employee, $promotion]))->assertOk();
    }

    public function test_an_offer_letter_that_states_pay_needs_the_permission_to_open()
    {
        Storage::fake(EmployeeDocument::DISK);
        Storage::disk(EmployeeDocument::DISK)->put('offer.pdf', 'pdf');
        $offer = Employee::factory()->create(['salary' => 50000]);
        $offer->forceFill(['offer_letter_path' => 'offer.pdf', 'offer_letter_name' => 'offer.pdf', 'offer_letter_kind' => OfferLetter::OFFER])->save();
        $welcome = Employee::factory()->create();
        $welcome->forceFill(['offer_letter_path' => 'offer.pdf', 'offer_letter_name' => 'welcome.pdf', 'offer_letter_kind' => OfferLetter::WELCOME])->save();
        $viewer = $this->withPermissions(['documents.view']);

        $this->actingAs($viewer)->get(route('admin.employees.onboarding.offer-letter', $offer))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.employees.onboarding.offer-letter', $welcome))->assertOk();
        $this->actingAs($this->withPermissions(['documents.view', 'employees.salary']))->get(route('admin.employees.onboarding.offer-letter', $offer))->assertOk();
    }

    public function test_the_intern_list_shows_who_is_paid_but_not_how_much_without_the_stipend_permission()
    {
        $this->intern();

        $this->actingAs($this->withPermissions(['interns.view']))
            ->get(route('admin.interns.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canSeePay', false)
                ->where('interns.data.0.has_stipend', true)
                ->where('interns.data.0.stipend', null));

        $this->actingAs($this->withPermissions(['interns.view', 'interns.stipend']))
            ->get(route('admin.interns.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('interns.data.0.stipend', '12000.00'));
    }

    public function test_an_intern_edit_without_the_stipend_permission_leaves_the_stipend_alone()
    {
        $intern = $this->intern();

        $this->actingAs($this->withPermissions(['interns.edit']))
            ->put(route('admin.interns.update', $intern), [
                'name' => 'Kavya S', 'email' => $intern->user->email, 'employee_code' => $intern->employee_code,
                'status' => 'active', 'has_stipend' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($intern->fresh()->has_stipend);
        $this->assertEquals(12000, $intern->fresh()->stipend);
    }
}
