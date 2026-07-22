@php
    $base = 'draft.'.$path;
    $field = \App\Enums\Field::tryFrom($node['field'] ?? '');
    $isBlank = trim((string) ($node['value'] ?? '')) === '';
    $readOnly ??= false;
@endphp

<flux:tooltip :content="$isBlank ? __('Blank condition — ignored when applied.') : null">
    <div @class(['flex flex-wrap items-center gap-2 sm:flex-nowrap', 'opacity-60' => $isBlank])>
        <flux:select
            wire:model.live="{{ $base }}.field"
            size="sm"
            class="w-full sm:w-[136px] sm:shrink-0"
            aria-label="{{ __('Field') }}"
            :disabled="$readOnly"
        >
            @foreach (\App\Enums\Field::cases() as $f)
                <flux:select.option value="{{ $f->value }}">{{ $f->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model.live="{{ $base }}.operator"
            size="sm"
            class="w-full sm:w-[184px] sm:shrink-0"
            aria-label="{{ __('Operator') }}"
            :disabled="$readOnly"
        >
            @foreach (($field?->operators() ?? []) as $op)
                <flux:select.option value="{{ $op->value }}">{{ $op->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="w-full min-w-[160px] sm:max-w-[280px]">
            @include('partials.filters.value', ['base' => $base, 'field' => $field, 'value' => $node['value'] ?? '', 'readOnly' => $readOnly])
        </div>

        <flux:button
            size="sm"
            variant="subtle"
            icon="x-mark"
            square
            aria-label="{{ __('Remove condition') }}"
            wire:click="removeNode('{{ $node['id'] }}')"
            :disabled="$readOnly"
        />
    </div>
</flux:tooltip>
