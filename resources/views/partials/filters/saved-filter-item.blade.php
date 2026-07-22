@php
    $isOwn = $savedFilter->user_id === auth()->id();
    $isLoaded = $savedFilter->id === $this->loadedFilterId;
@endphp

<div
    wire:key="filter-{{ $keyPrefix }}-{{ $savedFilter->id }}"
    data-search="{{ $savedFilter->name }} {{ $isOwn ? '' : $savedFilter->owner->name }}"
    class="group flex items-center gap-1 rounded-sm pe-1 hover:bg-zinc-100 dark:hover:bg-zinc-700"
>
    <button
        type="button"
        wire:click="load('{{ $savedFilter->id }}')"
        x-on:click="closeMenu()"
        class="flex min-w-0 flex-1 items-center gap-2 px-2 py-1.5 text-start"
    >
        <flux:icon.check @class(['size-4 shrink-0', 'opacity-0' => ! $isLoaded]) />
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm">{{ $savedFilter->name }}</span>
            @unless ($isOwn)
                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ __('by :owner', ['owner' => $savedFilter->owner->name]) }}</span>
            @endunless
        </span>
        @if ($isOwn && $savedFilter->is_shared)
            <flux:icon.users class="size-3.5 shrink-0 text-zinc-400" aria-label="{{ __('Shared with everyone') }}" />
        @endif
    </button>

    <flux:dropdown position="bottom" align="end">
        <flux:button
            size="xs"
            variant="ghost"
            icon="ellipsis-horizontal"
            x-on:click.stop
            aria-label="{{ __('Actions for :name', ['name' => $savedFilter->name]) }}"
        />

        <flux:menu>
            <flux:menu.item icon="play" wire:click="load('{{ $savedFilter->id }}')" x-on:click="closeMenu()">{{ __('Apply') }}</flux:menu.item>
            @if ($isOwn)
                <flux:menu.item icon="pencil" wire:click="openRename('{{ $savedFilter->id }}')" x-on:click="closeMenu()">{{ __('Rename') }}</flux:menu.item>
            @endif
            <flux:menu.item icon="document-duplicate" wire:click="duplicate('{{ $savedFilter->id }}')" x-on:click="closeMenu()">{{ __('Duplicate') }}</flux:menu.item>
            @if ($isOwn)
                <flux:menu.item icon="share" wire:click="toggleShare('{{ $savedFilter->id }}')" x-on:click="closeMenu()">
                    {{ $savedFilter->is_shared ? __('Unshare') : __('Share') }}
                </flux:menu.item>
            @endif
            <flux:menu.item icon="arrow-down-tray" wire:click="export('{{ $savedFilter->id }}')" x-on:click="closeMenu()">{{ __('Export JSON') }}</flux:menu.item>
            @if ($isOwn)
                <flux:menu.separator />
                <flux:menu.item variant="danger" icon="trash" wire:click="openDelete('{{ $savedFilter->id }}')" x-on:click="closeMenu()">{{ __('Delete') }}</flux:menu.item>
            @endif
        </flux:menu>
    </flux:dropdown>
</div>
