<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\EmployeeController;
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
