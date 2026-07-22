<div class="mx-auto flex w-full max-w-6xl flex-col gap-4">
    <flux:heading size="xl">{{ __('Users') }}</flux:heading>

    @if ($deadFilterMessage)
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="$deadFilterMessage">
            <x-slot name="controls">
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="x-mark"
                    square
                    aria-label="{{ __('Dismiss') }}"
                    wire:click="$set('deadFilterMessage', null)"
                />
            </x-slot>
        </flux:callout>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-2">
        @include('partials.filters.saved-filters-menu')

        <flux:button size="sm" variant="outline" icon="arrow-up-tray" wire:click="openImport">
            {{ __('Import') }}
        </flux:button>

        <div class="ms-auto">
            @if ($this->isSharedFilter)
                <flux:button size="sm" variant="outline" wire:click="openFork" :disabled="! $this->hasApplicableDraft">
                    {{ __('Save as my filter') }}
                </flux:button>
            @elseif ($this->loadedFilter)
                <div class="flex items-center gap-2">
                    @if ($this->isDirty)
                        <span class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                            <span aria-hidden="true" class="size-1.5 rounded-full bg-[var(--color-accent)]"></span>
                            {{ __('modified') }}
                        </span>
                    @endif

                    <flux:button.group>
                        <flux:button size="sm" variant="primary" wire:click="saveInPlace" :disabled="! $this->isDirty">
                            {{ __('Save') }}
                        </flux:button>

                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="primary" icon="chevron-down" aria-label="{{ __('More save options') }}" />

                            <flux:menu>
                                <flux:menu.item wire:click="openSaveAsNew" :disabled="! $this->hasApplicableDraft">
                                    {{ __('Save as new filter…') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:button.group>
                </div>
            @else
                <flux:button size="sm" variant="primary" wire:click="openSaveAsNew" :disabled="! $this->hasApplicableDraft">
                    {{ __('Save filter…') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Filter builder --}}
    <div
        x-data="{ expanded: true }"
        class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
    >
        <button
            type="button"
            x-on:click="expanded = ! expanded"
            class="flex w-full items-center gap-2 px-4 py-3 text-start text-sm font-medium"
        >
            <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform" x-bind:class="{ '-rotate-90': ! expanded }" />
            {{ __('Filters') }}
            @if ($this->loadedFilter)
                <flux:badge size="sm" color="zinc">{{ $this->loadedFilter->name }}</flux:badge>
            @endif
            @if ($this->isSharedFilter)
                <span class="ms-2 inline-flex items-center gap-1 text-xs font-normal text-zinc-500 dark:text-zinc-400">
                    <flux:icon.lock-closed class="size-3" />
                    {{ __('Shared filter — read-only') }}
                </span>
            @endif
        </button>

        <div x-show="expanded" class="border-t border-zinc-200 dark:border-zinc-700">
            <div class="p-4">
                @if ($this->isSharedFilter)
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">
                        <p>{{ __('This filter is shared with you. To make changes, save a copy as your own filter.') }}</p>
                        <flux:button size="sm" variant="outline" wire:click="openFork" :disabled="! $this->hasApplicableDraft">
                            {{ __('Save as my filter') }}
                        </flux:button>
                    </div>
                @endif

                @include('partials.filters.group', ['node' => $draft, 'path' => '', 'depth' => 0, 'readOnly' => $this->isSharedFilter])
            </div>

            <div class="flex items-center gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:button size="sm" variant="primary" wire:click="apply">{{ __('Apply filters') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="clearAll" :disabled="$this->isSharedFilter">{{ __('Clear all') }}</flux:button>
                <p class="ms-auto text-sm text-zinc-500 tabular-nums dark:text-zinc-400" aria-live="polite">
                    {{ $this->previewCount }} {{ trans_choice('result|results', $this->previewCount) }}
                </p>
            </div>
        </div>
    </div>

    {{-- Results --}}
    @island(name: 'results')
        @include('partials.users-table')
    @endisland

    @include('partials.filters.dialogs')
</div>
