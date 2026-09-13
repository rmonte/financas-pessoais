<?php

namespace App\Concerns;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;

/**
 * The "select a row, confirm in a modal, delete it" flow shared by every simple CRUD
 * index page. The host component still declares its own type-hinted confirmDelete()
 * method (Livewire resolves the clicked row's model from that type-hint) and calls
 * confirmDeletionOf() from it; everything else is provided by small hook methods.
 */
trait ConfirmsDeletion
{
    public ?int $deletingId = null;

    /**
     * Find the model to delete, scoped to the current user.
     */
    abstract protected function findForDeletion(int $id): Model;

    abstract protected function deletionModalName(): string;

    abstract protected function deletionSuccessMessage(): string;

    /**
     * Invalidate whatever cached listing the host keeps (e.g. `unset($this->banks)`).
     */
    abstract protected function afterDeletion(): void;

    protected function confirmDeletionOf(Model $model): void
    {
        $this->authorize('delete', $model);

        $this->deletingId = (int) $model->getKey();

        Flux::modal($this->deletionModalName())->show();
    }

    public function delete(): void
    {
        $model = $this->findForDeletion($this->deletingId);

        $this->authorize('delete', $model);

        $model->delete();

        $this->afterDeletion();
        $this->reset('deletingId');
        Flux::modal($this->deletionModalName())->close();
        Flux::toast(variant: 'success', text: $this->deletionSuccessMessage());
    }
}
