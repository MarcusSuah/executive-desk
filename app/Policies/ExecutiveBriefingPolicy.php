<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExecutiveBriefing;
use App\Models\User;

class ExecutiveBriefingPolicy
{
    /**
     * Determine whether the user can view the briefing.
     */
    public function view(User $user, ExecutiveBriefing $briefing): bool
    {
        return $briefing->user_id === $user->id || $user->can(Permission::ManageBriefings->value);
    }

    /**
     * Determine whether the user can generate a briefing for the given recipient.
     */
    public function generateFor(User $user, User $recipient): bool
    {
        return $recipient->id === $user->id || $user->can(Permission::ManageBriefings->value);
    }
}
