@php
    use App\Enums\Country;
    use App\Enums\Field;
    use App\Enums\Status;

    $readOnly ??= false;
    $value ??= '';
@endphp

@switch($field)
    @case(Field::Status)
        <flux:select wire:model.live="{{ $base }}.value" size="sm" placeholder="{{ __('Select status…') }}" aria-label="{{ __('Status value') }}" :disabled="$readOnly">
            @foreach (Status::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        @break

    @case(Field::Country)
        @php $selected = Country::tryFrom((string) $value); @endphp
        @if ($readOnly)
            <flux:input value="{{ $selected?->label() ?? $value }}" size="sm" readonly aria-label="{{ __('Country value') }}" />
        @else
            <x-searchable-listbox
                placeholder="{{ __('Search countries…') }}"
                empty="{{ __('No country found.') }}"
                width="w-[240px]"
            >
                <x-slot:trigger>
                    <flux:button size="sm" variant="outline" icon-trailing="chevrons-up-down" class="w-full justify-between font-normal" aria-label="{{ __('Country value') }}">
                        <span class="truncate {{ $selected === null ? 'text-zinc-400' : '' }}">
                            {{ $selected?->label() ?? __('Select country…') }}
                        </span>
                    </flux:button>
                </x-slot:trigger>

                @foreach (Country::cases() as $country)
                    <button
                        type="button"
                        data-search="{{ $country->label() }} {{ $country->value }}"
                        wire:key="country-{{ $base }}-{{ $country->value }}"
                        wire:click="$set('{{ $base }}.value', '{{ $country->value }}')"
                        x-on:click="closeMenu()"
                        class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                    >
                        <span class="min-w-0 flex-1 truncate">{{ $country->label() }}</span>
                        @if ($selected === $country)
                            <flux:icon.check class="size-4 shrink-0" />
                        @endif
                    </button>
                @endforeach
            </x-searchable-listbox>
        @endif
        @break

    @case(Field::CreatedAt)
        <flux:input type="date" wire:model.live="{{ $base }}.value" size="sm" aria-label="{{ __('Date value') }}" :disabled="$readOnly" />
        @break

    @default
        <flux:input wire:model.live.debounce.500ms="{{ $base }}.value" size="sm" placeholder="{{ __('Enter value…') }}" aria-label="{{ __('Condition value') }}" :disabled="$readOnly" />
@endswitch
