@php use App\Enums\Country; use App\Enums\Status; @endphp

<div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <flux:table :paginate="$this->users">
        <flux:table.columns>
            @foreach (['name' => __('Name'), 'email' => __('Email'), 'status' => __('Status'), 'country' => __('Country'), 'created_at' => __('Created at')] as $key => $label)
                <flux:table.column
                    sortable
                    :sorted="$this->sortField() === $key"
                    :direction="$this->sortDirection()"
                    wire:click="sortBy('{{ $key }}')"
                >{{ $label }}</flux:table.column>
            @endforeach
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell class="font-medium">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500 dark:text-zinc-400">{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($user->status) {
                            Status::Active => 'green',
                            Status::Pending => 'amber',
                            Status::Suspended => 'red',
                            Status::Deactivated => 'zinc',
                        }">{{ $user->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ Country::tryFrom($user->country ?? '')?->label() ?? $user->country ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                        {{ $user->created_at?->format('M j, Y') }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <div class="flex flex-col items-center gap-2 py-12 text-center">
                            <p class="font-medium">{{ __('No users match these filters') }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Try adjusting the conditions or clear the filters.') }}</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
