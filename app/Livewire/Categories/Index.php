<?php

namespace App\Livewire\Categories;

use App\Concerns\ConfirmsDeletion;
use App\Enums\CategoryType;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categorias')]
class Index extends Component
{
    use ConfirmsDeletion;

    public string $name = '';

    public string $type = CategoryType::Expense->value;

    public bool $is_active = true;

    public ?int $editingCategoryId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    #[Computed]
    public function categories(): Collection
    {
        return Auth::user()->categories()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Category::class);

        $this->reset('name', 'editingCategoryId');
        $this->type = CategoryType::Expense->value;
        $this->is_active = true;
        $this->resetValidation();

        Flux::modal('category-form')->show();
    }

    public function edit(Category $category): void
    {
        $this->authorize('update', $category);

        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->type = $category->type->value;
        $this->is_active = $category->is_active;
        $this->resetValidation();

        Flux::modal('category-form')->show();
    }

    public function save(): void
    {
        $user = Auth::user();
        $category = null;

        if ($this->editingCategoryId !== null) {
            $category = $user->categories()->findOrFail($this->editingCategoryId);
            $this->authorize('update', $category);
        } else {
            $this->authorize('create', Category::class);
        }

        $validated = $this->validate();

        if ($category) {
            $category->update($validated);
            Flux::toast(variant: 'success', text: __('Category updated.'));
        } else {
            $user->categories()->create($validated);
            Flux::toast(variant: 'success', text: __('Category created.'));
        }

        unset($this->categories);
        $this->reset('name', 'editingCategoryId');
        $this->type = CategoryType::Expense->value;
        $this->is_active = true;
        Flux::modal('category-form')->close();
    }

    public function confirmDelete(Category $category): void
    {
        $this->confirmDeletionOf($category);
    }

    protected function findForDeletion(int $id): Model
    {
        return Auth::user()->categories()->findOrFail($id);
    }

    protected function deletionModalName(): string
    {
        return 'confirm-category-deletion';
    }

    protected function deletionSuccessMessage(): string
    {
        return __('Category deleted.');
    }

    protected function afterDeletion(): void
    {
        unset($this->categories);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        $unique = Rule::unique(Category::class)->where(fn ($query) => $query->where('user_id', Auth::id()));

        if ($this->editingCategoryId !== null) {
            $unique = $unique->ignore($this->editingCategoryId);
        }

        return [
            'name' => ['required', 'string', 'max:100', $unique],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'is_active' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.categories.index');
    }
}
