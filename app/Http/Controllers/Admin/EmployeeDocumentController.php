<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmployeeDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
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
    /** Kilobytes, as Laravel's `max` file rule counts them. */
    public const MAX_KB = 10240;

    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(EmployeeDocumentType::class)],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::MIMES)],
            'expires_at' => ['nullable', 'date'],
        ]);

        $file = $request->file('file');

        // Stored under a random name: the original is kept only as a label, so
        // nothing a user types ever becomes part of a path on disk.
        $path = $file->store(EmployeeDocument::directoryFor($employee->id), EmployeeDocument::DISK);

        $employee->documents()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
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
