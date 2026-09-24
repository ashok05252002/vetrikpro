<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function destroy(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        abort_unless($comment->task_id === $task->id, 404);

        // Your own comment, or anyone's if you can administer people.
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->managesPeople(),
            403,
        );

        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}
