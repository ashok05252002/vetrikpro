<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MergeRequestInboxController;
use App\Http\Controllers\ProjectBoardController;
use App\Http\Controllers\Projects\BranchController;
use App\Http\Controllers\Projects\MergeRequestController;
use App\Http\Controllers\Projects\ProjectMemberController;
use App\Http\Controllers\Projects\ReferenceLookupController;
use App\Http\Controllers\Projects\RequirementController;
use App\Http\Controllers\Projects\TestPointController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
 * The app has no marketing page: the front door is the login screen, and a
 * signed-in visitor goes straight to their dashboard.
 */
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Projects and their boards — visible to members, not just administrators.
    Route::get('projects', [ProjectBoardController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectBoardController::class, 'show'])->name('projects.show');

    // Project workspace tabs. Each is its own route so it loads only its own data.
    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::get('members', [ProjectMemberController::class, 'index'])->name('members.index');
        Route::get('members/candidates', [ProjectMemberController::class, 'candidates'])->name('members.candidates');
        Route::post('members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::patch('members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');

        Route::get('lookups/{kind}', ReferenceLookupController::class)->whereIn('kind', ['tasks', 'test-points'])->name('lookups');

        // A test point is only ever reached through its own project.
        Route::scopeBindings()->group(function () {
            Route::get('testing', [TestPointController::class, 'index'])->name('testing.index');
            Route::post('testing', [TestPointController::class, 'store'])->name('testing.store');
            Route::get('testing/{testPoint}', [TestPointController::class, 'show'])->name('testing.show');
            Route::put('testing/{testPoint}', [TestPointController::class, 'update'])->name('testing.update');
            Route::patch('testing/{testPoint}/move', [TestPointController::class, 'move'])->name('testing.move');
            Route::delete('testing/{testPoint}', [TestPointController::class, 'destroy'])->name('testing.destroy');

            Route::get('requirements', [RequirementController::class, 'index'])->name('requirements.index');
            Route::post('requirements', [RequirementController::class, 'store'])->name('requirements.store');
            Route::get('requirements/{requirement}', [RequirementController::class, 'show'])->name('requirements.show');
            Route::put('requirements/{requirement}', [RequirementController::class, 'update'])->name('requirements.update');
            Route::delete('requirements/{requirement}', [RequirementController::class, 'destroy'])->name('requirements.destroy');
            Route::post('requirements/{requirement}/versions', [RequirementController::class, 'storeVersion'])->name('requirements.versions.store');
            Route::get('requirements/{requirement}/versions/{version}/download', [RequirementController::class, 'download'])->name('requirements.versions.download');

            // Developer module: branches registered by hand, and their merge requests.
            Route::get('git', [BranchController::class, 'index'])->name('git');
            Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
            Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
            Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::post('branches/{branch}/links', [BranchController::class, 'link'])->name('branches.links.store');
            Route::delete('branches/{branch}/links/{kind}/{id}', [BranchController::class, 'unlink'])->whereIn('kind', ['tasks', 'test-points'])->whereNumber('id')->name('branches.links.destroy');
            Route::post('branches/{branch}/close', [BranchController::class, 'close'])->name('branches.close');
            Route::post('branches/{branch}/merge-requests', [MergeRequestController::class, 'store'])->name('merge-requests.store');

            Route::get('merge-requests/{mergeRequest}', [MergeRequestController::class, 'show'])->name('merge-requests.show');
            Route::post('merge-requests/{mergeRequest}/transition', [MergeRequestController::class, 'transition'])->name('merge-requests.transition');
            Route::post('merge-requests/{mergeRequest}/comments', [MergeRequestController::class, 'comment'])->name('merge-requests.comments.store');
        });
    });

    Route::get('merge-requests', MergeRequestInboxController::class)->name('merge-requests.index');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
