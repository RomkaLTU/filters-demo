@props([
    'placeholder' => 'Search…',
    'empty' => 'No results found.',
    'width' => 'w-[300px]',
    'align' => 'start',
])

{{--
    A hand-rolled searchable popover: a trigger, a search box, and a scrollable
    list rendered server-side into the default slot. Alpine owns open/close and
    filters the list client-side by each item's `data-search` attribute; wrap a
    heading and its items in a `data-section` element to hide the heading when the
    section filters empty. Reused for the saved-filters menu and the country combobox.
--}}
<div
    x-data="{
        open: false,
        query: '',
        hasVisible: true,
        toggle() { this.open ? this.closeMenu() : this.openMenu(); },
        openMenu() {
            this.open = true;
            this.query = '';
            this.$nextTick(() => { this.filter(); this.$refs.search?.focus(); });
        },
        closeMenu() { this.open = false; },
        filter() {
            const needle = this.query.trim().toLowerCase();
            let visible = 0;
            this.$refs.list.querySelectorAll('[data-search]').forEach((item) => {
                const match = needle === '' || (item.getAttribute('data-search') || '').toLowerCase().includes(needle);
                item.hidden = ! match;
                if (match) { visible++; }
            });
            this.$refs.list.querySelectorAll('[data-section]').forEach((section) => {
                section.hidden = section.querySelectorAll('[data-search]:not([hidden])').length === 0;
            });
            this.hasVisible = visible > 0;
        },
    }"
    x-on:keydown.escape.stop="closeMenu()"
    x-on:click.outside="closeMenu()"
    class="relative"
>
    <div x-on:click="toggle()">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute {{ $align === 'end' ? 'end-0' : 'start-0' }} z-50 mt-1 {{ $width }} overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800"
    >
        <div class="border-b border-zinc-200 p-2 dark:border-zinc-700">
            <input
                x-ref="search"
                x-model="query"
                x-on:input="filter()"
                type="search"
                placeholder="{{ $placeholder }}"
                aria-label="{{ $placeholder }}"
                class="w-full rounded-md bg-transparent px-2 py-1.5 text-sm text-zinc-800 outline-none placeholder:text-zinc-400 dark:text-zinc-200"
            />
        </div>

        <div x-ref="list" class="max-h-72 overflow-y-auto p-1">
            {{ $slot }}
            <p x-show="! hasVisible" class="px-3 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ $empty }}
            </p>
        </div>
    </div>
</div>
