@php
    $base = $path === '' ? 'draft' : 'draft.'.$path;
    $children = is_array($node['children'] ?? null) ? $node['children'] : [];
    $readOnly ??= false;
@endphp

<div @class([
    'rounded-md border-s-2 border-zinc-200 bg-zinc-50 py-3 pe-3 ps-4 dark:border-zinc-700 dark:bg-zinc-800/40' => $depth > 0,
])>
    <div class="flex items-center gap-2">
        <flux:radio.group wire:model.live="{{ $base }}.boolean" variant="segmented" size="sm" :disabled="$readOnly">
            <flux:radio value="and">{{ __('AND') }}</flux:radio>
            <flux:radio value="or">{{ __('OR') }}</flux:radio>
        </flux:radio.group>

        @if ($depth > 0)
            <flux:spacer />
            <flux:button
                size="sm"
                variant="subtle"
                icon="x-mark"
                square
                aria-label="{{ __('Remove group') }}"
                wire:click="removeNode('{{ $node['id'] }}')"
                :disabled="$readOnly"
            />
        @endif
    </div>

    @if ($children === [])
        <p class="mt-3 rounded-md border border-dashed border-zinc-300 px-3 py-4 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            {{ __('No conditions yet. Add a condition to start filtering.') }}
        </p>
    @else
        <ul class="mt-3 flex flex-col gap-2">
            @foreach ($children as $i => $child)
                @php $childPath = $path === '' ? "children.{$i}" : "{$path}.children.{$i}"; @endphp
                <li wire:key="node-{{ $child['id'] }}">
                    @if (($child['type'] ?? null) === 'group')
                        @include('partials.filters.group', ['node' => $child, 'path' => $childPath, 'depth' => $depth + 1, 'readOnly' => $readOnly])
                    @else
                        @include('partials.filters.condition', ['node' => $child, 'path' => $childPath, 'depth' => $depth, 'readOnly' => $readOnly])
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-3 flex items-center gap-1">
        <flux:button size="sm" variant="ghost" icon="plus" wire:click="addCondition('{{ $node['id'] }}')" :disabled="$readOnly">
            {{ __('Add condition') }}
        </flux:button>

        @if ($depth < 2)
            <flux:button size="sm" variant="ghost" icon="plus" wire:click="addGroup('{{ $node['id'] }}')" :disabled="$readOnly">
                {{ __('Add group') }}
            </flux:button>
        @endif
    </div>
</div>
