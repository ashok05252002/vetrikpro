<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\Project;
use App\Models\TestPoint;
use App\Models\TestPointAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evidence images on a testing point: screenshots of what passed or failed.
 */
class TestPointAttachmentController extends Controller
{
    public function store(Request $request, Project $project, TestPoint $testPoint): RedirectResponse
    {
        $this->authorize('update', $testPoint);

        $room = TestPointAttachment::MAX_PER_POINT - $testPoint->attachments()->count();

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.max(1, $room)],
            // `image` refuses SVG by default; `mimes` pins the formats; video never passes either.
            'images.*' => ['file', 'image', 'mimes:'.implode(',', TestPointAttachment::MIMES), 'max:'.TestPointAttachment::MAX_KB],
        ], [
            'images.max' => $room <= 0
                ? 'This testing point already has '.TestPointAttachment::MAX_PER_POINT.' images. Remove some first.'
                : "You can add {$room} more image(s) to this testing point.",
            'images.*.image' => 'Only images can be attached — no videos or documents.',
            'images.*.mimes' => 'Use JPG, PNG, WebP or GIF.',
            'images.*.max' => 'Each image must be 2 MB or smaller.',
        ]);

        if ($room <= 0) {
            return back()->withErrors(['images' => 'This testing point already has '.TestPointAttachment::MAX_PER_POINT.' images.']);
        }

        DB::transaction(function () use ($request, $testPoint) {
            foreach ($request->file('images') as $image) {
                $testPoint->attachments()->create([
                    'file_path' => $image->store($testPoint->attachmentDirectory(), EmployeeDocument::DISK),
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $image->getMimeType() ?? 'image/png',
                    'size' => (int) $image->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);
            }
        });

        $count = count($request->file('images'));

        return back()->with('success', "{$count} ".str('image')->plural($count).' attached.');
    }

    /**
     * Streamed inline so it shows in an <img>, with the stored type and
     * nosniff, so a renamed file can never be run as something else.
     */
    public function show(Project $project, TestPoint $testPoint, TestPointAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $testPoint);

        return Storage::disk(EmployeeDocument::DISK)->response($attachment->file_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Request $request, Project $project, TestPoint $testPoint, TestPointAttachment $attachment): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $attachment->uploaded_by === $user->id || $user->can('projects.edit') || $project->owner_id === $user->id,
            403,
            'Only whoever attached it, the project owner or a project editor can remove this image.',
        );

        $attachment->delete();

        return back();
    }
}
