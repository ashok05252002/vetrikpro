<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration → Document types: the checklist employees are asked for.
 */
class DocumentTypeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/config/document-types', [
            'types' => DocumentType::ordered()->withCount('documents')->get()
                ->map(fn (DocumentType $type) => $type->only('id', 'name', 'code', 'description', 'is_required', 'states_pay', 'is_active', 'is_system', 'sort_order', 'documents_count')),
            'can' => [
                'create' => $request->user()->can('document_types.create'),
                'edit' => $request->user()->can('document_types.edit'),
                'delete' => $request->user()->can('document_types.delete'),
                // Whether a type states pay decides who sees its documents, so
                // only someone who may see pay can change it.
                'statesPay' => $request->user()->can('employees.salary'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DocumentType::create([
            ...$data,
            'code' => $this->uniqueCode($data['name']),
            'sort_order' => (int) DocumentType::max('sort_order') + 1,
        ]);

        return back()->with('success', "“{$data['name']}” added to the checklist.");
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        $data = $this->validated($request, $documentType);

        // The signed offer letter drives onboarding; it can be renamed but
        // never switched off.
        if ($documentType->is_system) {
            $data['is_active'] = true;
            $data['is_required'] = true;
        }

        $documentType->update($data);

        return back()->with('success', "“{$documentType->name}” updated.");
    }

    /**
     * Save a new order: the ids in the order they should appear.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:document_types,id'],
        ])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $position => $id) {
                DocumentType::whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return back();
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        if ($documentType->is_system) {
            return back()->with('error', "“{$documentType->name}” is part of onboarding and cannot be deleted.");
        }

        if ($documentType->documents()->exists()) {
            return back()->with('error', "Documents have already been uploaded as “{$documentType->name}”. Switch it off instead, so it stops being asked for.");
        }

        $documentType->delete();

        return back()->with('success', "“{$documentType->name}” removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DocumentType $type = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('document_types')->ignore($type?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_required' => ['required', 'boolean'],
            'states_pay' => $request->user()->can('employees.salary') ? ['sometimes', 'boolean'] : ['prohibited'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'document';
        $code = $base;

        for ($i = 2; DocumentType::where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        return $code;
    }
}
