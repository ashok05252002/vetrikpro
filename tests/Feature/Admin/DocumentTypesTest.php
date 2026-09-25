<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_checklist_exists_in_order_with_aadhaar_and_pan_required()
    {
        $types = DocumentType::ordered()->get();

        $this->assertSame(['aadhaar', 'pan'], $types->take(2)->pluck('code')->all());
        $this->assertTrue($types->firstWhere('code', 'aadhaar')->is_required);
        $this->assertTrue(DocumentType::signedOfferLetter()->is_system);
    }

    public function test_an_admin_adds_edits_and_reorders_types()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.config.document-types.store'), ['name' => 'Voter ID', 'description' => 'Both sides', 'is_required' => true, 'is_active' => true])
            ->assertSessionHas('success');

        $voter = DocumentType::where('code', 'voter_id')->firstOrFail();
        $this->assertTrue($voter->is_required);
        $this->assertSame(DocumentType::max('sort_order'), $voter->sort_order);

        $this->actingAs($admin)->put(route('admin.config.document-types.update', $voter), ['name' => 'Voter ID card', 'is_required' => false, 'is_active' => true]);
        $this->assertFalse($voter->fresh()->is_required);

        $ids = DocumentType::ordered()->pluck('id')->reverse()->values()->all();
        $this->actingAs($admin)->post(route('admin.config.document-types.reorder'), ['ids' => $ids]);
        $this->assertSame($ids, DocumentType::ordered()->pluck('id')->all());
    }

    public function test_names_are_unique()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.config.document-types.store'), ['name' => 'PAN card', 'is_required' => false, 'is_active' => true])
            ->assertSessionHasErrors('name');
    }

    public function test_the_signed_offer_letter_cannot_be_switched_off_or_deleted()
    {
        $admin = User::factory()->admin()->create();
        $signed = DocumentType::signedOfferLetter();

        $this->actingAs($admin)->put(route('admin.config.document-types.update', $signed), ['name' => 'Signed offer', 'is_required' => false, 'is_active' => false]);
        $signed->refresh();
        $this->assertSame('Signed offer', $signed->name);
        $this->assertTrue($signed->is_required && $signed->is_active);

        $this->actingAs($admin)->delete(route('admin.config.document-types.destroy', $signed))->assertSessionHas('error');
        $this->assertNotNull($signed->fresh());
    }

    public function test_a_type_in_use_is_switched_off_not_deleted()
    {
        Storage::fake(EmployeeDocument::DISK);
        $admin = User::factory()->admin()->create();
        $pan = DocumentType::where('code', 'pan')->first();
        $employee = Employee::factory()->create();
        $this->actingAs($admin)->post(route('admin.employees.documents.store', $employee), [
            'document_type_id' => $pan->id, 'title' => 'PAN', 'file' => UploadedFile::fake()->create('pan.pdf', 50, 'application/pdf'),
        ]);

        $this->actingAs($admin)->delete(route('admin.config.document-types.destroy', $pan))->assertSessionHas('error');
        $this->assertNotNull($pan->fresh());

        $unused = DocumentType::where('code', 'photo')->first();
        $this->actingAs($admin)->delete(route('admin.config.document-types.destroy', $unused))->assertSessionHas('success');
        $this->assertNull($unused->fresh());
    }

    public function test_inactive_types_are_not_offered_for_upload()
    {
        Storage::fake(EmployeeDocument::DISK);
        $admin = User::factory()->admin()->create();
        $hidden = DocumentType::where('code', 'contract')->first();
        $hidden->update(['is_active' => false]);
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.employees.documents.store', $employee), [
                'document_type_id' => $hidden->id, 'title' => 'x', 'file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('document_type_id');

        $this->actingAs($admin)
            ->get(route('admin.employees.documents.index', $employee))
            ->assertInertia(fn (Assert $page) => $page->where('types', fn ($types) => ! collect($types)->contains('label', 'Contract')));
    }

    public function test_the_checklist_needs_its_own_permissions()
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.config.document-types.index'))->assertForbidden();

        $reader = User::factory()->create();
        $reader->syncPermissionOverrides(['document_types.view' => true]);
        $this->actingAs($reader)->get(route('admin.config.document-types.index'))->assertOk();
        $this->actingAs($reader)
            ->post(route('admin.config.document-types.store'), ['name' => 'X', 'is_required' => false, 'is_active' => true])
            ->assertForbidden();
    }
}
