<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Type-ahead search for pickers like the project owner, so a form never has
 * to load every account into a dropdown.
 */
class UserLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['projects.create', 'projects.edit']), 403);

        $users = UserDirectory::filter(User::query(), $request)
            ->with(UserDirectory::with())
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (User $user) => UserDirectory::row($user));

        return response()->json(['data' => $users]);
    }
}
