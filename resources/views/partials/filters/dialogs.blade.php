{{-- Save filter / Save as my filter --}}
<flux:modal name="save-filter" class="w-full sm:max-w-sm">
    <form wire:submit="saveFilter" class="flex flex-col gap-5">
        <div>
            <flux:heading size="lg">{{ $saveMode === 'fork' ? __('Save as my filter') : __('Save filter') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Saved filters appear in the saved-filters menu in the toolbar.') }}</flux:text>
        </div>

        <flux:input
            wire:model="saveName"
            label="{{ __('Filter name') }}"
            placeholder="{{ __('e.g. Active Gmail users') }}"
            autofocus
        />

        <flux:field variant="inline">
            <flux:label>{{ __('Share with everyone') }}</flux:label>
            <flux:spacer />
            <flux:switch wire:model="saveShared" />
        </flux:field>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">
                {{ $saveMode === 'fork' ? __('Save as my filter') : __('Save filter') }}
            </flux:button>
        </div>
    </form>
</flux:modal>

{{-- Rename --}}
<flux:modal name="rename-filter" class="w-full sm:max-w-sm">
    <form wire:submit="rename" class="flex flex-col gap-5">
        <flux:heading size="lg">{{ __('Rename filter') }}</flux:heading>

        <flux:input wire:model="renameName" label="{{ __('Filter name') }}" autofocus />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Rename') }}</flux:button>
        </div>
    </form>
</flux:modal>

{{-- Delete confirmation --}}
<flux:modal name="delete-filter" class="w-full sm:max-w-md">
    <div class="flex flex-col gap-5">
        <div>
            <flux:heading size="lg">{{ __('Delete “:name”?', ['name' => $this->deleteTarget?->name]) }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('This permanently deletes the saved filter. Anyone it was shared with will lose access. This action cannot be undone.') }}
            </flux:text>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="deleteFilter">{{ __('Delete filter') }}</flux:button>
        </div>
    </div>
</flux:modal>

{{-- Import --}}
@php $importPlaceholder = '{"version": 1, "root": { "type": "group", … }}'; @endphp
<flux:modal name="import-filter" class="w-full sm:max-w-md">
    <div
        class="flex flex-col gap-4"
        x-data="{
            readFile(file) {
                if (! file) { return; }
                const reader = new FileReader();
                reader.onload = () => { $wire.set('importText', String(reader.result ?? '')); };
                reader.readAsText(file);
            },
        }"
    >
        <div>
            <flux:heading size="lg">{{ __('Import filter') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Drop a JSON file or paste exported filter JSON below.') }}</flux:text>
        </div>

        <button
            type="button"
            x-on:click="$refs.file.click()"
            x-on:dragover.prevent="$el.classList.add('border-zinc-400', 'dark:border-zinc-500')"
            x-on:dragleave.prevent="$el.classList.remove('border-zinc-400', 'dark:border-zinc-500')"
            x-on:drop.prevent="$el.classList.remove('border-zinc-400', 'dark:border-zinc-500'); readFile($event.dataTransfer.files?.[0])"
            class="flex flex-col items-center gap-2 rounded-md border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 transition-colors hover:border-zinc-400 dark:border-zinc-600 dark:text-zinc-400"
        >
            <flux:icon.arrow-up-tray class="size-5" />
            <span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Choose a file') }}</span>
                {{ __('or drag it here') }}
            </span>
            <span class="text-xs">{{ __('.json exported from this app') }}</span>
        </button>
        <input
            x-ref="file"
            type="file"
            accept=".json,application/json"
            class="sr-only"
            aria-label="{{ __('Filter JSON file') }}"
            x-on:change="readFile($event.target.files?.[0])"
        />

        <flux:textarea
            wire:model="importText"
            label="{{ __('Or paste JSON') }}"
            rows="5"
            class="font-mono text-xs"
            :placeholder="$importPlaceholder"
        />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="import">{{ __('Import filter') }}</flux:button>
        </div>
    </div>
</flux:modal>
