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
            'user_id' => $user->id,
            'role' => $user->role?->only('id', 'name', 'is_super'),
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
            'counts' => [
                'documents' => $viewer->can('employees.documents') ? $employee->documents()->count() : null,
                'projects' => $user->projects()->count(),
                'open_tasks' => $user->assignedTasks()->where('status', '!=', TaskStatus::Done)->count(),
            ],
            'viewer' => [
                'can_edit' => $viewer->can('employees.manage'),
                'can_documents' => $viewer->can('employees.documents'),
                // Changing someone's access needs roles.manage *and* holding
                // everything they already hold.
                'can_access' => $viewer->can('roles.manage') && $viewer->canGrant($user->permissions()),
            ],
        ];
    }
}
