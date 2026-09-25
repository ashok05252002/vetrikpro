<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\EmployeeProfileController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserLookupController;
use Illuminate\Support\Facades\Route;

/*
 * Each section is gated by its own permission (see App\Support\Permissions),
 * not by a single "admin area" door: a role can be given employees without
 * users, or projects without people, and the routes follow.
 */
Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', UserController::class)->except('show')->middleware('can:users.manage');
        Route::resource('roles', RoleController::class)->except('show')->middleware('can:roles.manage');

        Route::resource('departments', DepartmentController::class)->except('show')->middleware('can:masters.manage');
        Route::resource('designations', DesignationController::class)->except('show')->middleware('can:masters.manage');

        // Staff profile tabs. Overview is employees.show; the rest hang off it.
        Route::prefix('employees/{employee}')->name('employees.')->scopeBindings()->group(function () {
            Route::middleware('can:employees.documents')->group(function () {
                Route::get('documents', [EmployeeProfileController::class, 'documents'])->name('documents.index');
                Route::post('documents', [EmployeeDocumentController::class, 'store'])->name('documents.store');
                Route::get('documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('documents.download');
                Route::delete('documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
            });

            Route::middleware('can:employees.view')->group(function () {
                Route::get('projects', [EmployeeProfileController::class, 'projects'])->name('projects');
                Route::get('tasks', [EmployeeProfileController::class, 'tasks'])->name('tasks');
            });

            Route::middleware('can:roles.manage')->group(function () {
                Route::get('access', [EmployeeProfileController::class, 'access'])->name('access');
                Route::put('access', [EmployeeProfileController::class, 'updateAccess'])->name('access.update');
            });
        });

        Route::resource('employees', EmployeeController::class)
            ->middlewareFor(['index', 'show'], 'can:employees.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:employees.manage');

        Route::resource('projects', ProjectController::class)->except('show')->middleware('can:projects.manage');
        Route::get('lookups/users', UserLookupController::class)->middleware('can:projects.manage')->name('lookups.users');

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });
