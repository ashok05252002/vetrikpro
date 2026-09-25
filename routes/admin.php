<?php

use App\Http\Controllers\Admin\Config\DocumentTypeController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\EmployeeOnboardingController;
use App\Http\Controllers\Admin\EmployeeProfileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserLookupController;
use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Support\Facades\Route;

/*
 * Each section is gated by its own permissions (see App\Support\Permissions),
 * one per action, not by a single "admin area" door: a role can see employees
 * without editing them, or edit projects without deleting them.
 */
/**
 * Gate a resource's actions separately: index/show need view, create/store
 * need create, edit/update need edit, destroy needs delete.
 */
$crud = fn (PendingResourceRegistration $resource, string $module) => $resource
    ->middlewareFor(['index', 'show'], "can:{$module}.view")
    ->middlewareFor(['create', 'store'], "can:{$module}.create")
    ->middlewareFor(['edit', 'update'], "can:{$module}.edit")
    ->middlewareFor('destroy', "can:{$module}.delete");

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () use ($crud) {
        $crud(Route::resource('users', UserController::class)->except('show'), 'users');
        Route::patch('users/{user}/status', [UserController::class, 'status'])->middleware('can:users.edit')->name('users.status');
        $crud(Route::resource('roles', RoleController::class)->except('show'), 'roles');

        $crud(Route::resource('departments', DepartmentController::class)->except('show'), 'departments');
        $crud(Route::resource('designations', DesignationController::class)->except('show'), 'designations');

        // Staff profile tabs. Overview is employees.show; the rest hang off it.
        Route::prefix('employees/{employee}')->name('employees.')->scopeBindings()->group(function () {
            Route::get('documents', [EmployeeProfileController::class, 'documents'])->middleware('can:documents.view')->name('documents.index');
            Route::get('documents/{document}/download', [EmployeeDocumentController::class, 'download'])->middleware('can:documents.view')->name('documents.download');
            Route::post('documents', [EmployeeDocumentController::class, 'store'])->middleware('can:documents.create')->name('documents.store');
            Route::delete('documents/{document}', [EmployeeDocumentController::class, 'destroy'])->middleware('can:documents.delete')->name('documents.destroy');

            Route::get('onboarding', [EmployeeOnboardingController::class, 'show'])->middleware('can:employees.view')->name('onboarding');
            Route::get('onboarding/offer-letter', [EmployeeOnboardingController::class, 'downloadOfferLetter'])->middleware('can:documents.view')->name('onboarding.offer-letter');
            Route::middleware('can:employees.onboard')->prefix('onboarding')->name('onboarding.')->group(function () {
                Route::post('invite', [EmployeeOnboardingController::class, 'invite'])->name('invite');
                Route::post('offer-letter', [EmployeeOnboardingController::class, 'uploadOfferLetter'])->name('offer-letter.store');
                Route::post('approve', [EmployeeOnboardingController::class, 'approve'])->name('approve');
                Route::post('send-back', [EmployeeOnboardingController::class, 'sendBack'])->name('send-back');
            });

            Route::middleware('can:employees.view')->group(function () {
                Route::get('projects', [EmployeeProfileController::class, 'projects'])->name('projects');
                Route::get('tasks', [EmployeeProfileController::class, 'tasks'])->name('tasks');
            });

            // Changing one person's access is editing access.
            Route::middleware('can:roles.edit')->group(function () {
                Route::get('access', [EmployeeProfileController::class, 'access'])->name('access');
                Route::put('access', [EmployeeProfileController::class, 'updateAccess'])->name('access.update');
            });
        });

        $crud(Route::resource('employees', EmployeeController::class), 'employees');

        $crud(Route::resource('projects', ProjectController::class)->except('show'), 'projects');
        // The owner picker is used while creating or editing a project; the controller checks either.
        Route::get('lookups/users', UserLookupController::class)->name('lookups.users');

        // Configuration: organisation settings and the document checklist.
        Route::prefix('config')->name('config.')->group(function () {
            Route::get('document-types', [DocumentTypeController::class, 'index'])->middleware('can:document_types.view')->name('document-types.index');
            Route::post('document-types', [DocumentTypeController::class, 'store'])->middleware('can:document_types.create')->name('document-types.store');
            Route::post('document-types/reorder', [DocumentTypeController::class, 'reorder'])->middleware('can:document_types.edit')->name('document-types.reorder');
            Route::put('document-types/{documentType}', [DocumentTypeController::class, 'update'])->middleware('can:document_types.edit')->name('document-types.update');
            Route::delete('document-types/{documentType}', [DocumentTypeController::class, 'destroy'])->middleware('can:document_types.delete')->name('document-types.destroy');
        });

        Route::get('settings', [SettingsController::class, 'edit'])->middleware('can:settings.view')->name('settings.edit');
        Route::post('settings', [SettingsController::class, 'update'])->middleware('can:settings.edit')->name('settings.update');
    });
