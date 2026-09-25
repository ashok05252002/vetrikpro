<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

/**
 * Searching people by name, email, employee code, department and designation.
 *
 * One query shape shared by every place that has to find a person among many:
 * the project member list, the "add members" dialog and the owner picker.
 * Department and designation live on the employee record, so a user with no
 * employee profile only matches when those filters are empty.
 */
final class UserDirectory
{
    /**
     * Accepts a relation as well as a plain query, so a project's member list
     * keeps its pivot columns.
     *
     * @template T of Builder<User>|BelongsToMany<User, *>
     *
     * @param  T  $query
     * @return T
     */
    public static function filter(Builder|BelongsToMany $query, Request $request): Builder|BelongsToMany
    {
        return $query
            ->when($request->string('search')->trim()->value(), fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->where('users.name', 'like', "%{$search}%")
                ->orWhere('users.email', 'like', "%{$search}%")
                ->orWhereHas('employee', fn (Builder $e) => $e->where('employee_code', 'like', "%{$search}%"))))
            ->when($request->integer('department'), fn (Builder $q, int $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('department_id', $id)))
            ->when($request->integer('designation'), fn (Builder $q, int $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('designation_id', $id)));
    }

    /**
     * The eager loads a directory row needs.
     *
     * @return array<int, string>
     */
    public static function with(): array
    {
        return ['employee:id,user_id,employee_code,department_id,designation_id', 'employee.department:id,name', 'employee.designation:id,name'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(User $user): array
    {
        return [
            ...$user->only('id', 'name', 'email', 'is_active'),
            'employee_id' => $user->employee?->id,
            'employee_code' => $user->employee?->employee_code,
            'department' => $user->employee?->department?->name,
            'designation' => $user->employee?->designation?->name,
        ];
    }

    /**
     * Options for the department and designation filter dropdowns.
     *
     * @return array<string, mixed>
     */
    public static function filterOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $d) => ['value' => (string) $d->id, 'label' => $d->name]),
            'designations' => Designation::orderBy('name')->get(['id', 'name'])
                ->map(fn (Designation $d) => ['value' => (string) $d->id, 'label' => $d->name]),
        ];
    }
}
