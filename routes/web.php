<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MergeRequestInboxController;
use App\Http\Controllers\Onboarding\InvitationController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\ProjectBoardController;
use App\Http\Controllers\Projects\BranchController;
use App\Http\Controllers\Projects\MergeRequestController;
use App\Http\Controllers\Projects\ProjectMemberController;
use App\Http\Controllers\Projects\ReferenceLookupController;
use App\Http\Controllers\Projects\RequirementController;
use App\Http\Controllers\Projects\TestPointAttachmentController;
use App\Http\Controllers\Projects\TestPointController;
use App\Http\Controllers\Projects\TestRunController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TestingController;
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

// The welcome email's link. Guests only: it signs the person in at the end.
Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation', [InvitationController::class, 'store'])->name('invitation.store');
});

// A new employee's own "complete your profile" page.
Route::middleware('auth')->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'show'])->name('show');
    Route::put('details', [OnboardingController::class, 'updateDetails'])->name('details');
    Route::put('bank', [OnboardingController::class, 'updateBank'])->name('bank');
    Route::post('documents', [OnboardingController::class, 'uploadDocument'])->name('documents.store');
    Route::get('documents/{document}/download', [OnboardingController::class, 'downloadDocument'])->name('documents.download');
    Route::delete('documents/{document}', [OnboardingController::class, 'deleteDocument'])->name('documents.destroy');
    Route::get('offer-letter', [OnboardingController::class, 'downloadOfferLetter'])->name('offer-letter');
    Route::post('submit', [OnboardingController::class, 'submit'])->name('submit');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Projects and their boards — visible to members, not just administrators.
    Route::get('projects', [ProjectBoardController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectBoardController::class, 'show'])->name('projects.show');

    // Project workspace tabs. Each is its own route so it loads only its own data.
    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        // The project's bugs, in the project; runs and bug pages stay in the Testing module.
        Route::get('testing', [TestPointController::class, 'project'])->name('testing');
        Route::get('members', [ProjectMemberController::class, 'index'])->name('members.index');
        Route::get('members/candidates', [ProjectMemberController::class, 'candidates'])->name('members.candidates');
        Route::post('members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::patch('members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');

        Route::get('lookups/{kind}', ReferenceLookupController::class)->whereIn('kind', ['tasks', 'test-points'])->name('lookups');

        // Requirements, the developer module and merge requests belong to their project.
        Route::scopeBindings()->group(function () {
            // Requirements: versions of requirement points, from Excel or typed in.
            Route::get('requirements', [RequirementController::class, 'index'])->name('requirements.index');
            Route::get('requirements/template', [RequirementController::class, 'template'])->name('requirements.template');
            Route::get('requirements/upload', [RequirementController::class, 'upload'])->name('requirements.upload');
            Route::post('requirements/check', [RequirementController::class, 'check'])->name('requirements.check');
            Route::post('requirements/import', [RequirementController::class, 'import'])->name('requirements.import');
            Route::get('requirements/manual', [RequirementController::class, 'manual'])->name('requirements.manual');
            Route::post('requirements/manual', [RequirementController::class, 'storeManual'])->name('requirements.manual.store');

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

    // Testing is its own module: every project a person can see, then that
    // project's testing points and test runs. A point or run is only ever
    // reached through its own project.
    Route::get('testing', TestingController::class)->name('testing.index');

    Route::prefix('testing/{project}')->name('testing.')->scopeBindings()->group(function () {
        Route::get('/', [TestPointController::class, 'index'])->name('points.index');
        Route::post('points', [TestPointController::class, 'store'])->name('points.store');
        Route::get('points/{testPoint}', [TestPointController::class, 'show'])->name('points.show');
        Route::put('points/{testPoint}', [TestPointController::class, 'update'])->name('points.update');
        Route::patch('points/{testPoint}/move', [TestPointController::class, 'move'])->name('points.move');
        Route::delete('points/{testPoint}', [TestPointController::class, 'destroy'])->name('points.destroy');
        Route::post('points/{testPoint}/attachments', [TestPointAttachmentController::class, 'store'])->name('points.attachments.store');
        Route::get('points/{testPoint}/attachments/{attachment}', [TestPointAttachmentController::class, 'show'])->name('points.attachments.show');
        Route::delete('points/{testPoint}/attachments/{attachment}', [TestPointAttachmentController::class, 'destroy'])->name('points.attachments.destroy');

        Route::get('runs', [TestRunController::class, 'index'])->name('runs.index');
        Route::post('runs', [TestRunController::class, 'store'])->name('runs.store');
        Route::get('runs/{testRun}', [TestRunController::class, 'show'])->name('runs.show');
        Route::post('runs/{testRun}/complete', [TestRunController::class, 'complete'])->name('runs.complete');
        Route::post('runs/{testRun}/reopen', [TestRunController::class, 'reopen'])->name('runs.reopen');
        Route::delete('runs/{testRun}', [TestRunController::class, 'destroy'])->name('runs.destroy');
        Route::patch('runs/{testRun}/results/{result}', [TestRunController::class, 'record'])->name('runs.results.update');
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
require __DIR__.'/accounts.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Unknown addresses go through the web middleware too, so the 404 page knows
// who is signed in and the company's name (bootstrap/app.php renders it).
// Every method, so a POST to an unknown address is still a 404, not a 405.
Route::any('{fallbackPlaceholder}', fn () => abort(404))->where('fallbackPlaceholder', '.*')->fallback();
