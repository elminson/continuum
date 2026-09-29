<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;

class RegionPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Region $region): bool
    {
        return $region->is_preset || ($user !== null && $region->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Region $region): bool
    {
        return ! $region->is_preset && $region->user_id === $user->id;
    }

    public function delete(User $user, Region $region): bool
    {
        return ! $region->is_preset && $region->user_id === $user->id;
    }

    public function backfill(User $user, Region $region): bool
    {
        return $region->is_preset || $region->user_id === $user->id;
    }
}
