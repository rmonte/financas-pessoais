<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
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
    public function view(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->user_id;
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
     *
     * Transactions linked to an investment operation are system-managed and can only be
     * changed by editing that operation, so its own amount and the transaction stay in sync.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->user_id && $transaction->investment_operation_id === null;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Transactions linked to an investment operation are system-managed and can only be
     * removed by deleting that operation.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->user_id && $transaction->investment_operation_id === null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Transaction $transaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return false;
    }
}
