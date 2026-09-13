<?php

namespace App\Policies;

use App\Models\InvestmentOperation;
use App\Models\User;

class InvestmentOperationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InvestmentOperation $investmentOperation): bool
    {
        return $user->id === $investmentOperation->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InvestmentOperation $investmentOperation): bool
    {
        return $user->id === $investmentOperation->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InvestmentOperation $investmentOperation): bool
    {
        return $user->id === $investmentOperation->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InvestmentOperation $investmentOperation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InvestmentOperation $investmentOperation): bool
    {
        return false;
    }
}
