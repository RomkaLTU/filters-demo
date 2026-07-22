@php
    $recent = $this->recentFilters;
    $mine = $this->visibleFilters->where('user_id', auth()->id());
    $shared = $this->visibleFilters->where('user_id', '!=', auth()->id());
@endphp

<x-searchable-listbox
    placeholder="{{ __('Search filters…') }}"
    empty="{{ __('No filters found.') }}"
    width="w-[320px]"
    align="start"
>
    <x-slot:trigger>
        <flux:button
            size="sm"
            variant="outline"
            icon="bookmark"
            icon-trailing="chevrons-up-down"
            class="w-[220px] justify-between font-normal"
        >
            <span class="truncate">{{ $this->loadedFilter?->name ?? __('Saved filters') }}</span>
        </flux:button>
    </x-slot:trigger>

    @if ($this->visibleFilters->isEmpty())
        <div class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
            <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('No saved filters yet') }}</p>
            <p class="mt-1">{{ __('Build a filter below and save it to reuse it later.') }}</p>
        </div>
    @else
        @if ($recent->isNotEmpty())
            <div data-section class="pb-1">
                <p class="px-2 py-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Recent') }}</p>
                @foreach ($recent as $savedFilter)
                    @include('partials.filters.saved-filter-item', ['savedFilter' => $savedFilter, 'keyPrefix' => 'recent'])
                @endforeach
            </div>
            <flux:separator variant="subtle" class="my-1" />
        @endif

        <div data-section class="pb-1">
            <p class="px-2 py-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('My filters') }}</p>
            @forelse ($mine as $savedFilter)
                @include('partials.filters.saved-filter-item', ['savedFilter' => $savedFilter, 'keyPrefix' => 'mine'])
            @empty
                <p class="px-2 py-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('No saved filters yet.') }}</p>
            @endforelse
        </div>

        @if ($shared->isNotEmpty())
            <flux:separator variant="subtle" class="my-1" />
            <div data-section class="pb-1">
                <p class="px-2 py-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Shared with me') }}</p>
                @foreach ($shared as $savedFilter)
                    @include('partials.filters.saved-filter-item', ['savedFilter' => $savedFilter, 'keyPrefix' => 'shared'])
                @endforeach
            </div>
        @endif
    @endif
</x-searchable-listbox>
