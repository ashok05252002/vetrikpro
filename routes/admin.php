<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'manages-people'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::resource('departments', DepartmentController::class)->except('show');
        Route::resource('designations', DesignationController::class)->except('show');
        Route::resource('employees', EmployeeController::class);
        Route::resource('projects', ProjectController::class)->except('show');

        // Organisation settings are the administrator's alone, not HR's.
        Route::middleware('admin')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });
