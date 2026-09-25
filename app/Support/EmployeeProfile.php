<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\User;

/**
 * The header every tab of a staff profile renders, plus which tabs the viewer
 * may open. Each tab controller adds only its own data.
 */
final class EmployeeProfile
{
    /**
     * @return array<string, mixed>
     */
    public static function header(Employee $employee, User $viewer): array
    {
        $employee->loadMissing(['user.role:id,name,slug,is_super', 'department:id,name', 'designation:id,name']);
        $user = $employee->user;

        return [
            ...$employee->only('id', 'employee_code', 'status', 'employment_type', 'date_of_joining'),
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'deactivated_at' => $user->deactivated_at,
            'deactivated_by' => $user->deactivated_by ? User::whereKey($user->deactivated_by)->value('name') : null,
            'user_id' => $user->id,
            'role' => $user->role?->only('id', 'name', 'is_super'),
            'department' => $employee->department?->name,
            'onboarding_status' => $employee->onboarding_status?->value,
            'designation' => $employee->designation?->name,
            'counts' => [
                'documents' => $viewer->can('documents.view') ? $employee->documents()->count() : null,
                'projects' => $user->projects()->count(),
                'open_tasks' => $user->assignedTasks()->where('status', '!=', TaskStatus::Done)->count(),
            ],
            'viewer' => [
                'can_edit' => $viewer->can('employees.edit'),
                'can_documents' => $viewer->can('documents.view'),
                'can_upload' => $viewer->can('documents.create'),
                'can_delete_documents' => $viewer->can('documents.delete'),
                // Changing someone's access needs roles.edit *and* holding
                // everything they already hold.
                'can_access' => $viewer->can('roles.edit') && $viewer->canGrant($user->permissions()),
                'can_toggle_access' => $viewer->can('users.edit') && ! $viewer->is($user) && $viewer->canGrant($user->permissions()),
            ],
        ];
    }
}
