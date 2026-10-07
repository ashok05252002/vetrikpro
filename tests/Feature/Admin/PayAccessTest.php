<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\OfferLetter;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Salaries and stipends are seen and set only with employees.salary and
 * interns.stipend; everyone else gets the record without the amounts.
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

    public function test_promoting_includes_seeing_salaries()
    {
        $this->assertContains('employees.salary', Permissions::only(['employees.promote']));
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
