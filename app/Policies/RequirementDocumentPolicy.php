<?php

namespace App\Policies;

use App\Models\RequirementDocument;
use App\Models\User;

/**
 * Everyone on a project reads its requirements. Adding documents and new
 * versions is for the people who define the work: project managers, the
 * owner, and the project's dev admins.
 */
class RequirementDocumentPolicy
{
    public function view(User $user, RequirementDocument $document): bool
    {
        return $document->project->isAccessibleBy($user);
    }

    public function create(User $user, RequirementDocument $document): bool
    {
        $project = $document->project;

        return $user->can('projects.edit') || $project->owner_id === $user->id || $project->isDevAdmin($user);
    }

    public function update(User $user, RequirementDocument $document): bool
    {
        return $this->create($user, $document);
    }

    public function delete(User $user, RequirementDocument $document): bool
    {
        return $user->can('projects.edit') || $document->project->owner_id === $user->id;
    }
}
