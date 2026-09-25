<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents are private: they sit on the `documents` disk, which has no
 * public route, and every download passes through here.
 */
class EmployeeDocumentController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'file' => Uploads::documentRule(),
            'expires_at' => ['nullable', 'date'],
        ]);

        $file = $request->file('file');

        $path = $file->store(EmployeeDocument::directoryFor($employee->id), EmployeeDocument::DISK);

        $employee->documents()->create([
            'document_type_id' => $data['document_type_id'],
            'title' => $data['title'],
            'file_path' => $path,
            ...Uploads::meta($file),
            'expires_at' => $data['expires_at'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', "“{$data['title']}” uploaded.");
    }

    public function download(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        return Storage::disk(EmployeeDocument::DISK)->download($document->file_path, $document->original_name);
    }

    public function destroy(Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        $document->delete();

        return back()->with('success', "“{$document->title}” deleted.");
    }
}
