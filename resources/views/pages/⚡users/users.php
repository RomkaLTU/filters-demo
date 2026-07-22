<?php

use App\Data\FilterDefinition;
use App\Data\FilterTreeNormalizer;
use App\Data\GroupData;
use App\Enums\BooleanOperator;
use App\Enums\Field;
use App\Enums\Operator;
use App\Exceptions\InvalidFilterDefinition;
use App\Models\SavedFilter;
use App\Models\User;
use App\Queries\FilteredUsersQuery;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Users')] class extends Component
{
    use WithPagination;

    /**
     * The builder tree being edited: nested node arrays carrying an ephemeral
     * `id` per node (for wire:key), matching Phase 1's Draft shape.
     *
     * @var array<string, mixed>
     */
    public array $draft = [];

    /**
     * The Canonical Applied Definition currently shaping the table (a normalized
     * root-group array), or null when the table is unfiltered.
     *
     * @var array<string, mixed>|null
     */
    public ?array $applied = null;

    /** The Saved Filter the draft was loaded from, for dirty comparison. */
    public ?string $loadedFilterId = null;

    /** Present only while a clean Saved Filter is applied; cleared once dirty. */
    #[Url]
    public ?string $filter = null;

    /** The Spatie sort string: `name` (asc) or `-name` (desc). */
    #[Url]
    public ?string $sort = null;

    /** Banner text shown when a dead or stale `?filter` was on the URL at load. */
    public ?string $deadFilterMessage = null;

    /** Save dialog: whether it is a fresh save (`new`) or a fork of a shared filter (`fork`). */
    public string $saveMode = 'new';

    /** Save dialog: the name being entered. */
    public string $saveName = '';

    /** Save dialog: the share-with-everyone toggle. */
    public bool $saveShared = false;

    /** Rename dialog: the filter being renamed and the name being entered. */
    public ?string $renameId = null;

    public string $renameName = '';

    /** Delete dialog: the filter pending permanent deletion. */
    public ?string $deleteId = null;

    /** Import dialog: the raw JSON pasted or read from a dropped file. */
    public string $importText = '';

    public function mount(): void
    {
        $this->draft = $this->emptyRoot();

        if ($this->filter === null) {
            return;
        }

        $filter = SavedFilter::query()->visibleTo($this->currentUser())->find($this->filter);

        if ($filter === null) {
            $this->deadFilterMessage = __('That saved filter is no longer available.');
            $this->filter = null;

            return;
        }

        try {
            $definition = FilterDefinition::fromEnvelope($filter->definition);
        } catch (InvalidFilterDefinition) {
            $this->deadFilterMessage = __("':name' can't be applied — it's no longer valid.", ['name' => $filter->name]);
            $this->filter = null;

            return;
        }

        $this->loadedFilterId = $filter->id;
        $this->draft = $this->treeWithIds(FilterDefinition::toEnvelope($definition)['root']);
        $this->applied = FilterTreeNormalizer::normalize($this->draft);
    }

    // -- Builder tree mutations ------------------------------------------------

    public function updatedDraft(mixed $value, ?string $key = null): void
    {
        if ($key !== null && str_ends_with($key, 'field')) {
            $this->cascadeFieldChange($key);
        }

        $this->afterDraftChange();
    }

    public function addCondition(string $groupId): void
    {
        $this->draft = $this->treeAddChild($this->draft, $groupId, $this->newCondition());
        $this->afterDraftChange();
    }

    public function addGroup(string $groupId): void
    {
        $this->draft = $this->treeAddChild($this->draft, $groupId, $this->newGroup());
        $this->afterDraftChange();
    }

    public function removeNode(string $nodeId): void
    {
        $this->draft = $this->treeRemove($this->draft, $nodeId);
        $this->afterDraftChange();
    }

    // -- Applying, clearing, sorting ------------------------------------------

    public function apply(): void
    {
        $this->applied = FilterTreeNormalizer::normalize($this->draft);
        $this->resetPage();
        $this->syncFilterUrl();

        if ($this->loadedFilterId !== null && ! $this->isDirty && ($filter = $this->loadedFilter) !== null) {
            $this->recordRecent($filter);
        }

        $this->refreshResults();
    }

    public function clearAll(): void
    {
        $this->draft = $this->emptyRoot();
        $this->applied = null;
        $this->loadedFilterId = null;
        $this->filter = null;
        $this->resetPage();
        $this->forgetFilterState();
        $this->refreshResults();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['name', 'email', 'status', 'country', 'created_at'], true)) {
            return;
        }

        if ($this->sortField() === $field) {
            $this->sort = $this->sortDirection() === 'asc' ? '-'.$field : $field;
        } else {
            $this->sort = $field;
        }

        $this->resetPage();
        unset($this->users);
    }

    // -- Loading a saved filter ------------------------------------------------

    public function load(string $id): void
    {
        $filter = SavedFilter::query()->visibleTo($this->currentUser())->find($id);

        if ($filter === null) {
            Flux::toast(variant: 'warning', text: __('That saved filter is no longer available.'));

            return;
        }

        try {
            $definition = FilterDefinition::fromEnvelope($filter->definition);
        } catch (InvalidFilterDefinition) {
            Flux::toast(variant: 'warning', text: __("':name' can't be applied — it's no longer valid.", ['name' => $filter->name]));

            return;
        }

        $this->applyLoadedFilter($filter, $definition);
        $this->recordRecent($filter);
    }

    // -- Saving ----------------------------------------------------------------

    public function saveInPlace(): void
    {
        $filter = $this->loadedFilter;

        if ($filter === null || $filter->user_id !== $this->currentUser()->id || ! $this->isDirty) {
            return;
        }

        $normalized = FilterTreeNormalizer::normalize($this->draft);

        if ($normalized === null) {
            return;
        }

        try {
            $definition = FilterDefinition::fromRoot($normalized);
        } catch (InvalidFilterDefinition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $filter->update(['definition' => FilterDefinition::toEnvelope($definition)]);
        $this->applied = $normalized;
        $this->forgetFilterState();
        $this->syncFilterUrl();
        $this->recordRecent($filter);
        $this->refreshResults();

        Flux::toast(variant: 'success', text: __('Saved ":name".', ['name' => $filter->name]));
    }

    public function openSaveAsNew(): void
    {
        $this->saveMode = 'new';
        $this->saveName = '';
        $this->saveShared = false;
        $this->resetErrorBag('saveName');
        Flux::modal('save-filter')->show();
    }

    public function openFork(): void
    {
        $filter = $this->loadedFilter;

        if ($filter === null) {
            return;
        }

        $this->saveMode = 'fork';
        $this->saveName = $this->availableName($filter->name, 'mine');
        $this->saveShared = false;
        $this->resetErrorBag('saveName');
        Flux::modal('save-filter')->show();
    }

    public function saveFilter(): void
    {
        $name = trim($this->saveName);

        if ($name === '') {
            $this->addError('saveName', __('Please enter a name for this filter.'));

            return;
        }

        if (mb_strlen($name) > 100) {
            $this->addError('saveName', __('The filter name may not be longer than 100 characters.'));

            return;
        }

        if ($this->nameTaken($name)) {
            $this->addError('saveName', __('You already have a filter with this name.'));

            return;
        }

        $normalized = FilterTreeNormalizer::normalize($this->draft);

        if ($normalized === null) {
            $this->addError('saveName', __('Add at least one condition before saving.'));

            return;
        }

        try {
            $definition = FilterDefinition::fromRoot($normalized);
        } catch (InvalidFilterDefinition $exception) {
            $this->addError('saveName', $exception->getMessage());

            return;
        }

        $filter = $this->currentUser()->savedFilters()->create([
            'name' => $name,
            'definition' => FilterDefinition::toEnvelope($definition),
            'is_shared' => $this->saveShared,
        ]);

        $this->loadedFilterId = $filter->id;
        $this->applied = $normalized;
        $this->forgetFilterState();
        unset($this->visibleFilters);
        $this->syncFilterUrl();
        $this->recordRecent($filter);
        $this->refreshResults();

        Flux::modal('save-filter')->close();
        Flux::toast(variant: 'success', text: __('Saved ":name".', ['name' => $name]));
    }

    // -- Rename / duplicate / share / delete -----------------------------------

    public function openRename(string $id): void
    {
        $filter = $this->currentUser()->savedFilters()->find($id);

        if ($filter === null) {
            return;
        }

        $this->renameId = $filter->id;
        $this->renameName = $filter->name;
        $this->resetErrorBag('renameName');
        Flux::modal('rename-filter')->show();
    }

    public function rename(): void
    {
        if ($this->renameId === null) {
            return;
        }

        $filter = $this->currentUser()->savedFilters()->find($this->renameId);

        if ($filter === null) {
            $this->renameId = null;

            return;
        }

        $name = trim($this->renameName);

        if ($name === '') {
            $this->addError('renameName', __('Please enter a name for this filter.'));

            return;
        }

        if (mb_strlen($name) > 100) {
            $this->addError('renameName', __('The filter name may not be longer than 100 characters.'));

            return;
        }

        if ($this->nameTaken($name, exceptId: $filter->id)) {
            $this->addError('renameName', __('You already have a filter with this name.'));

            return;
        }

        $filter->update(['name' => $name]);
        $this->renameId = null;
        $this->forgetFilterState();
        unset($this->visibleFilters, $this->recentFilters);

        Flux::modal('rename-filter')->close();
        Flux::toast(variant: 'success', text: __('Renamed to ":name".', ['name' => $name]));
    }

    public function duplicate(string $id): void
    {
        $source = SavedFilter::query()->visibleTo($this->currentUser())->find($id);

        if ($source === null) {
            Flux::toast(variant: 'warning', text: __('That saved filter is no longer available.'));

            return;
        }

        try {
            $definition = FilterDefinition::fromEnvelope($source->definition);
        } catch (InvalidFilterDefinition) {
            Flux::toast(variant: 'warning', text: __("':name' can't be applied — it's no longer valid.", ['name' => $source->name]));

            return;
        }

        $name = $this->availableName($source->name, 'copy');

        $this->currentUser()->savedFilters()->create([
            'name' => $name,
            'definition' => FilterDefinition::toEnvelope($definition),
            'is_shared' => false,
        ]);

        unset($this->visibleFilters);

        Flux::toast(variant: 'success', text: __('Duplicated as ":name".', ['name' => $name]));
    }

    public function toggleShare(string $id): void
    {
        $filter = $this->currentUser()->savedFilters()->find($id);

        if ($filter === null) {
            return;
        }

        $filter->update(['is_shared' => ! $filter->is_shared]);
        $this->forgetFilterState();
        unset($this->visibleFilters, $this->recentFilters);

        Flux::toast(variant: 'success', text: $filter->is_shared
            ? __('Shared ":name" with everyone.', ['name' => $filter->name])
            : __('Unshared ":name".', ['name' => $filter->name]));
    }

    public function openDelete(string $id): void
    {
        $filter = $this->currentUser()->savedFilters()->find($id);

        if ($filter === null) {
            return;
        }

        $this->deleteId = $filter->id;
        Flux::modal('delete-filter')->show();
    }

    public function deleteFilter(): void
    {
        if ($this->deleteId === null) {
            return;
        }

        $filter = $this->currentUser()->savedFilters()->find($this->deleteId);
        $this->deleteId = null;

        if ($filter === null) {
            Flux::modal('delete-filter')->close();

            return;
        }

        $name = $filter->name;
        $wasLoaded = $this->loadedFilterId === $filter->id;

        $filter->delete();

        if ($wasLoaded) {
            $this->loadedFilterId = null;
            $this->filter = null;
        }

        $this->forgetFilterState();
        unset($this->visibleFilters, $this->recentFilters);

        Flux::modal('delete-filter')->close();
        Flux::toast(variant: 'success', text: __('Deleted ":name".', ['name' => $name]));
    }

    // -- Import / export -------------------------------------------------------

    public function openImport(): void
    {
        $this->reset('importText');
        $this->resetErrorBag('importText');
        Flux::modal('import-filter')->show();
    }

    public function import(): void
    {
        $decoded = json_decode($this->importText, associative: true);

        if (! is_array($decoded)) {
            $this->addError('importText', __("That doesn't look like valid JSON."));

            return;
        }

        try {
            $definition = FilterDefinition::fromEnvelope($decoded);
        } catch (InvalidFilterDefinition $exception) {
            $this->addError('importText', $exception->getMessage());

            return;
        }

        $this->loadedFilterId = null;
        $this->filter = null;
        $this->draft = $this->treeWithIds(FilterDefinition::toEnvelope($definition)['root']);
        $this->applied = FilterTreeNormalizer::normalize($this->draft);
        $this->resetPage();
        $this->forgetFilterState();
        $this->refreshResults();

        Flux::modal('import-filter')->close();
        Flux::toast(variant: 'success', text: __('Filter imported — save it to keep it.'));
    }

    public function export(string $id): ?StreamedResponse
    {
        $filter = SavedFilter::query()->visibleTo($this->currentUser())->find($id);

        if ($filter === null) {
            Flux::toast(variant: 'warning', text: __('That saved filter is no longer available.'));

            return null;
        }

        try {
            $definition = FilterDefinition::fromEnvelope($filter->definition);
        } catch (InvalidFilterDefinition) {
            Flux::toast(variant: 'warning', text: __("':name' can't be applied — it's no longer valid.", ['name' => $filter->name]));

            return null;
        }

        $payload = FilterDefinition::toEnvelope($definition);
        $payload['name'] = $filter->name;
        $payload['exported_at'] = now()->toIso8601String();

        $filename = (Str::slug($filter->name) ?: 'filter').'.json';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    // -- Computed --------------------------------------------------------------

    /**
     * The paginated users for the applied definition — evaluated only inside the
     * results island, so builder edits never re-run this query.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return app(FilteredUsersQuery::class)
            ->builder($this->appliedDefinition(), $this->sort)
            ->paginate(10);
    }

    /** The live count of users matching the Draft, recomputed each round-trip. */
    #[Computed]
    public function previewCount(): int
    {
        return app(FilteredUsersQuery::class)->builder($this->draftDefinition())->count();
    }

    /** A loaded Saved Filter whose Draft no longer matches its stored Definition. */
    #[Computed]
    public function isDirty(): bool
    {
        $filter = $this->loadedFilter;

        if ($filter === null) {
            return false;
        }

        $storedRoot = is_array($filter->definition['root'] ?? null) ? $filter->definition['root'] : null;

        if ($storedRoot === null) {
            return false;
        }

        return FilterTreeNormalizer::normalize($this->draft) != FilterTreeNormalizer::normalize($storedRoot);
    }

    /** Whether the loaded filter belongs to another user (rendered read-only). */
    #[Computed]
    public function isSharedFilter(): bool
    {
        $filter = $this->loadedFilter;

        return $filter !== null && $filter->user_id !== $this->currentUser()->id;
    }

    /** Whether the current Draft normalizes to a saveable Definition. */
    #[Computed]
    public function hasApplicableDraft(): bool
    {
        return FilterTreeNormalizer::normalize($this->draft) !== null;
    }

    #[Computed]
    public function loadedFilter(): ?SavedFilter
    {
        if ($this->loadedFilterId === null) {
            return null;
        }

        return SavedFilter::query()->visibleTo($this->currentUser())->with('owner')->find($this->loadedFilterId);
    }

    #[Computed]
    public function deleteTarget(): ?SavedFilter
    {
        if ($this->deleteId === null) {
            return null;
        }

        return $this->currentUser()->savedFilters()->find($this->deleteId);
    }

    /**
     * The current user's own and shared-with-them filters — the menu's My filters
     * and Shared with me sections.
     *
     * @return Collection<int, SavedFilter>
     */
    #[Computed]
    public function visibleFilters(): Collection
    {
        return SavedFilter::query()
            ->visibleTo($this->currentUser())
            ->with('owner')
            ->orderBy('name')
            ->get();
    }

    /**
     * The three most recently used filters still visible to the user.
     *
     * @return Collection<int, SavedFilter>
     */
    #[Computed]
    public function recentFilters(): Collection
    {
        return $this->currentUser()->recentFilters()
            ->visibleTo($this->currentUser())
            ->with('owner')
            ->orderByPivot('last_used_at', 'desc')
            ->take(3)
            ->get();
    }

    public function sortField(): string
    {
        return ltrim($this->sort ?? 'name', '-');
    }

    public function sortDirection(): string
    {
        return str_starts_with($this->sort ?? 'name', '-') ? 'desc' : 'asc';
    }

    // -- Internals -------------------------------------------------------------

    private function applyLoadedFilter(SavedFilter $filter, GroupData $definition): void
    {
        $this->loadedFilterId = $filter->id;
        $this->draft = $this->treeWithIds(FilterDefinition::toEnvelope($definition)['root']);
        $this->applied = FilterTreeNormalizer::normalize($this->draft);
        $this->resetPage();
        $this->forgetFilterState();
        $this->syncFilterUrl();
        $this->refreshResults();
    }

    private function recordRecent(SavedFilter $filter): void
    {
        $this->currentUser()->recentFilters()->syncWithoutDetaching([
            $filter->getKey() => ['last_used_at' => now()],
        ]);

        unset($this->recentFilters);
    }

    /**
     * Whether the current user already owns a filter with this name
     * (case-insensitive), optionally excluding one filter (for rename).
     */
    private function nameTaken(string $name, ?string $exceptId = null): bool
    {
        return $this->currentUser()->savedFilters()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * Build a non-colliding name from a stem and a parenthetical tag, e.g.
     * "Active users (copy)", then "Active users (copy 2)", "… (copy 3)".
     */
    private function availableName(string $stem, string $tag): string
    {
        $candidate = sprintf('%s (%s)', $stem, $tag);

        for ($n = 2; $this->nameTaken($candidate); $n++) {
            $candidate = sprintf('%s (%s %d)', $stem, $tag, $n);
        }

        return mb_substr($candidate, 0, 100);
    }

    private function appliedDefinition(): ?GroupData
    {
        return $this->applied === null ? null : $this->safeDefinition($this->applied);
    }

    private function draftDefinition(): ?GroupData
    {
        $normalized = FilterTreeNormalizer::normalize($this->draft);

        return $normalized === null ? null : $this->safeDefinition($normalized);
    }

    /**
     * Hydrate a canonical root array into a validated {@see GroupData}. The array
     * is already normalized, so this cannot fail in practice; a failure degrades
     * loudly to unfiltered rather than throwing mid-render.
     *
     * @param  array<string, mixed>  $root
     */
    private function safeDefinition(array $root): ?GroupData
    {
        try {
            return FilterDefinition::fromRoot($root);
        } catch (InvalidFilterDefinition) {
            return null;
        }
    }

    private function afterDraftChange(): void
    {
        $this->forgetFilterState();
        unset($this->previewCount, $this->hasApplicableDraft);
        $this->syncFilterUrl();
    }

    private function syncFilterUrl(): void
    {
        $this->forgetFilterState();
        $this->filter = ($this->loadedFilterId !== null && ! $this->isDirty) ? $this->loadedFilterId : null;
    }

    private function forgetFilterState(): void
    {
        unset($this->isDirty, $this->loadedFilter, $this->isSharedFilter);
    }

    private function refreshResults(): void
    {
        unset($this->users);
        $this->renderIsland('results');
    }

    private function cascadeFieldChange(string $key): void
    {
        $nodePath = Str::beforeLast($key, '.field');

        if ($nodePath === $key) {
            return;
        }

        $raw = data_get($this->draft, $key);
        $field = is_string($raw) ? Field::tryFrom($raw) : null;

        if ($field === null) {
            return;
        }

        data_set($this->draft, $nodePath.'.operator', $field->operators()[0]->value);
        data_set($this->draft, $nodePath.'.value', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRoot(): array
    {
        return [
            'id' => $this->nodeId(),
            'type' => 'group',
            'boolean' => BooleanOperator::And->value,
            'children' => [$this->newCondition()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newCondition(): array
    {
        return [
            'id' => $this->nodeId(),
            'type' => 'condition',
            'field' => Field::Name->value,
            'operator' => Operator::Equals->value,
            'value' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newGroup(): array
    {
        return [
            'id' => $this->nodeId(),
            'type' => 'group',
            'boolean' => BooleanOperator::Or->value,
            'children' => [$this->newCondition()],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $child
     * @return array<string, mixed>
     */
    private function treeAddChild(array $node, string $groupId, array $child): array
    {
        if (($node['type'] ?? null) !== 'group') {
            return $node;
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        if (($node['id'] ?? null) === $groupId) {
            $children[] = $child;
            $node['children'] = $children;

            return $node;
        }

        $node['children'] = array_map(
            fn (mixed $c): mixed => is_array($c) ? $this->treeAddChild($c, $groupId, $child) : $c,
            $children,
        );

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function treeRemove(array $node, string $id): array
    {
        if (($node['type'] ?? null) !== 'group' || ! is_array($node['children'] ?? null)) {
            return $node;
        }

        $children = [];

        foreach ($node['children'] as $child) {
            if (is_array($child) && ($child['id'] ?? null) === $id) {
                continue;
            }

            $children[] = is_array($child) ? $this->treeRemove($child, $id) : $child;
        }

        $node['children'] = $children;

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function treeWithIds(array $node): array
    {
        $node['id'] = $this->nodeId();

        if (($node['type'] ?? null) === 'group' && is_array($node['children'] ?? null)) {
            $node['children'] = array_map(
                fn (mixed $c): mixed => is_array($c) ? $this->treeWithIds($c) : $c,
                $node['children'],
            );
        }

        return $node;
    }

    private function nodeId(): string
    {
        return Str::uuid()->toString();
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
};
