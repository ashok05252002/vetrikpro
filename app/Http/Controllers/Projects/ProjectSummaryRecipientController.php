<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectSummaryRecipient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The people who get a project's daily summary besides its owner: anyone
 * with an account, or an outside address. Added and removed one at a time,
 * like members, so two people editing the list never undo each other.
 * Whoever manages the project's members manages this list.
 */
class ProjectSummaryRecipientController extends Controller
{
    private const MAX = 30;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'user_id' => ['nullable', 'required_without:email', 'prohibits:email', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'email' => ['nullable', 'required_without:user_id', 'string', 'email:rfc', 'max:255'],
        ], [
            'user_id.prohibits' => 'Choose a person or type an address, not both.',
            'email.required_without' => 'Choose a person or type an email address.',
        ]);

        if ($project->summaryRecipients()->count() >= self::MAX) {
            return back()->with('error', 'Up to '.self::MAX.' people can get this project\'s summary.');
        }

        $email = isset($data['email']) ? strtolower(trim($data['email'])) : null;
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        // An address that belongs to an account is stored as that account, so
        // it follows the person if their email changes and stops if they leave.
        if ($email !== null) {
            $userId = User::whereRaw('lower(email) = ?', [$email])->value('id');
            $email = $userId === null ? $email : null;
        }

        if ($userId !== null && $userId === $project->owner_id) {
            return back()->with('success', 'The owner already gets this project\'s summary.');
        }

        $recipient = $project->summaryRecipients()->firstOrCreate(
            $userId !== null ? ['user_id' => $userId] : ['email' => $email],
            ['added_by' => $request->user()->id],
        );

        return back()->with('success', $recipient->wasRecentlyCreated
            ? 'Added to the daily summary.'
            : 'They already get this project\'s summary.');
    }

    public function destroy(Project $project, ProjectSummaryRecipient $summaryRecipient): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $summaryRecipient->delete();

        return back()->with('success', 'Removed from the daily summary.');
    }
}
