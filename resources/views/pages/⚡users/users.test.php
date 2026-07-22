<?php

use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A draft root group wrapping a single condition, with the ephemeral node ids
 * the builder carries.
 *
 * @return array<string, mixed>
 */
function draftWith(string $field, string $operator, string $value): array
{
    return [
        'id' => 'g-root',
        'type' => 'group',
        'boolean' => 'and',
        'children' => [
            ['id' => 'c-1', 'type' => 'condition', 'field' => $field, 'operator' => $operator, 'value' => $value],
        ],
    ];
}

it('renders the page for an authenticated user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('users'))
        ->assertOk()
        ->assertSeeLivewire('pages::users');
});

it('renders an editor for every field type in the builder', function () {
    $this->actingAs(User::factory()->create());

    $draft = [
        'id' => 'g', 'type' => 'group', 'boolean' => 'and', 'children' => [
            ['id' => 'c1', 'type' => 'condition', 'field' => 'name', 'operator' => 'contains', 'value' => ''],
            ['id' => 'c2', 'type' => 'condition', 'field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ['id' => 'c3', 'type' => 'condition', 'field' => 'country', 'operator' => 'equals', 'value' => 'US'],
            ['id' => 'c4', 'type' => 'condition', 'field' => 'created_at', 'operator' => 'greater_than', 'value' => '2025-01-01'],
        ],
    ];

    Livewire::test('pages::users')
        ->set('draft', $draft)
        ->assertOk()
        ->assertSee('United States')
        ->assertSee('greater than (after)');
});

it('promotes the normalized draft to the applied definition and resets the page on apply', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::users')
        ->set('draft', draftWith('status', 'equals', 'active'))
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->call('apply')
        ->assertSet('applied', [
            'type' => 'group',
            'boolean' => 'and',
            'children' => [
                ['type' => 'condition', 'field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ],
        ])
        ->assertSet('paginators.page', 1);
});

it('leaves the table unfiltered when the applied draft prunes to nothing', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::users')
        ->set('draft', draftWith('name', 'contains', '   '))
        ->call('apply')
        ->assertSet('applied', null);
});

it('loads a saved filter clean, syncing the filter id into the URL', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->condition(Field::Status, Operator::Equals, Status::Active->value)->create();

    $this->actingAs($user);

    Livewire::test('pages::users')
        ->call('load', $filter->id)
        ->assertSet('loadedFilterId', $filter->id)
        ->assertSet('filter', $filter->id)
        ->assertDontSee(__('modified'));
});

it('marks a loaded filter dirty and clears the filter id from the URL once the draft diverges', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->condition(Field::Status, Operator::Equals, Status::Active->value)->create();

    $this->actingAs($user);

    Livewire::test('pages::users')
        ->call('load', $filter->id)
        ->assertSet('filter', $filter->id)
        ->set('draft.children.0.value', Status::Pending->value)
        ->assertSet('filter', null)
        ->assertSee(__('modified'));
});

it('saves a dirty owned filter in place, restoring the clean URL state', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->condition(Field::Status, Operator::Equals, Status::Active->value)->create();

    $this->actingAs($user);

    Livewire::test('pages::users')
        ->call('load', $filter->id)
        ->set('draft.children.0.value', Status::Suspended->value)
        ->assertSet('filter', null)
        ->call('saveInPlace')
        ->assertSet('filter', $filter->id)
        ->assertDontSee(__('modified'));

    expect($filter->fresh()->definition['root']['children'][0]['value'])->toBe(Status::Suspended->value);
});

it('does not save when the loaded filter is not dirty', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->condition(Field::Status, Operator::Equals, Status::Active->value)->create();

    $this->actingAs($user);

    Livewire::test('pages::users')
        ->call('load', $filter->id)
        ->call('saveInPlace');

    expect($filter->fresh()->definition['root']['children'][0]['value'])->toBe(Status::Active->value);
});

// -- Save family: save as new, fork shared -----------------------------------

it('saves the current draft as a new filter, loading it clean', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::users')
        ->set('draft', draftWith('status', 'equals', 'active'))
        ->call('openSaveAsNew')
        ->set('saveName', 'Active users')
        ->set('saveShared', true)
        ->call('saveFilter')
        ->assertHasNoErrors();

    $filter = $user->savedFilters()->firstWhere('name', 'Active users');

    expect($filter)->not->toBeNull()
        ->and($filter->is_shared)->toBeTrue()
        ->and($filter->definition['root']['children'][0]['value'])->toBe(Status::Active->value);
});

it('rejects a save whose name collides case-insensitively for the owner', function () {
    $user = User::factory()->create();
    SavedFilter::factory()->ownedBy($user)->create(['name' => 'Active Users']);

    $this->actingAs($user);

    Livewire::test('pages::users')
        ->set('draft', draftWith('status', 'equals', 'active'))
        ->call('openSaveAsNew')
        ->set('saveName', 'active users')
        ->call('saveFilter')
        ->assertHasErrors('saveName');

    expect($user->savedFilters()->count())->toBe(1);
});

it('will not save a draft that prunes to nothing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::users')
        ->set('draft', draftWith('name', 'contains', '   '))
        ->call('openSaveAsNew')
        ->set('saveName', 'Empty')
        ->call('saveFilter')
        ->assertHasErrors('saveName');

    expect($user->savedFilters()->count())->toBe(0);
});

it('forks a shared filter into a private copy owned by the viewer', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $shared = SavedFilter::factory()->ownedBy($owner)->shared()
        ->condition(Field::Status, Operator::Equals, Status::Active->value)
        ->create(['name' => 'Team view']);

    $this->actingAs($viewer);

    Livewire::test('pages::users')
        ->call('load', $shared->id)
        ->assertSet('loadedFilterId', $shared->id)
        ->assertSet('filter', $shared->id)
        ->assertSee(__('Shared filter — read-only'))
        ->call('openFork')
        ->assertSet('saveName', 'Team view (mine)')
        ->call('saveFilter')
        ->assertHasNoErrors();

    expect($viewer->savedFilters()->where('name', 'Team view (mine)')->where('is_shared', false)->exists())->toBeTrue();
});

it('auto-increments the fork name suffix when the copy already exists', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $shared = SavedFilter::factory()->ownedBy($owner)->shared()->create(['name' => 'Team view']);
    SavedFilter::factory()->ownedBy($viewer)->create(['name' => 'Team view (mine)']);

    $this->actingAs($viewer);

    Livewire::test('pages::users')
        ->call('load', $shared->id)
        ->call('openFork')
        ->assertSet('saveName', 'Team view (mine 2)');
});

// -- Import ------------------------------------------------------------------

it('imports a valid definition as an unsaved draft', function () {
    $this->actingAs(User::factory()->create());

    $json = json_encode([
        'version' => 1,
        'root' => [
            'type' => 'group',
            'boolean' => 'and',
            'children' => [
                ['type' => 'condition', 'field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ],
        ],
    ]);

    Livewire::test('pages::users')
        ->call('openImport')
        ->set('importText', $json)
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('loadedFilterId', null)
        ->assertSet('filter', null)
        ->assertSet('applied', [
            'type' => 'group',
            'boolean' => 'and',
            'children' => [
                ['type' => 'condition', 'field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ],
        ]);
});

it('surfaces the positional first error when importing an invalid definition', function () {
    $this->actingAs(User::factory()->create());

    $json = json_encode([
        'version' => 1,
        'root' => [
            'type' => 'group',
            'boolean' => 'and',
            'children' => [
                ['type' => 'condition', 'field' => 'age', 'operator' => 'equals', 'value' => '5'],
            ],
        ],
    ]);

    $component = Livewire::test('pages::users')
        ->call('openImport')
        ->set('importText', $json)
        ->call('import')
        ->assertHasErrors('importText')
        ->assertSet('loadedFilterId', null)
        ->assertSet('applied', null);

    expect($component->instance()->getErrorBag()->first('importText'))
        ->toBe('Unknown field "age" in group 1, condition 1.');
});

it('rejects import text that is not valid JSON', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::users')
        ->call('openImport')
        ->set('importText', 'not json at all')
        ->call('import')
        ->assertHasErrors('importText')
        ->assertSet('applied', null);
});

// -- Authorization -----------------------------------------------------------

it('refuses to load a filter that is neither owned nor shared', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $private = SavedFilter::factory()->ownedBy($owner)->create();

    $this->actingAs($viewer);

    Livewire::test('pages::users')
        ->call('load', $private->id)
        ->assertSet('loadedFilterId', null)
        ->assertSet('applied', null);
});

it('ignores owner-only actions on a filter the user does not own', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $shared = SavedFilter::factory()->ownedBy($owner)->shared()->create(['name' => 'Owned by someone else']);

    $this->actingAs($viewer);

    $component = Livewire::test('pages::users');

    $component->call('openRename', $shared->id)->assertSet('renameId', null);
    $component->call('openDelete', $shared->id)->assertSet('deleteId', null);
    $component->call('toggleShare', $shared->id);
    $component->set('deleteId', $shared->id)->call('deleteFilter');

    expect($shared->fresh())->not->toBeNull()
        ->and($shared->fresh()->is_shared)->toBeTrue()
        ->and($shared->fresh()->name)->toBe('Owned by someone else');
});

it('duplicates a shared filter but not an invisible one', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $shared = SavedFilter::factory()->ownedBy($owner)->shared()->create(['name' => 'Shared one']);
    $private = SavedFilter::factory()->ownedBy($owner)->create(['name' => 'Private one']);

    $this->actingAs($viewer);

    $component = Livewire::test('pages::users');
    $component->call('duplicate', $shared->id);
    $component->call('duplicate', $private->id);

    expect($viewer->savedFilters()->where('name', 'Shared one (copy)')->where('is_shared', false)->exists())->toBeTrue()
        ->and($viewer->savedFilters()->where('name', 'Private one (copy)')->exists())->toBeFalse();
});

it('exports a saved filter as a downloadable JSON file', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)
        ->condition(Field::Status, Operator::Equals, Status::Active->value)
        ->create(['name' => 'Active users']);

    $this->actingAs($user);

    Livewire::test('pages::users')
        ->call('export', $filter->id)
        ->assertFileDownloaded('active-users.json');
});

it('deletes an owned filter and clears it from the loaded state', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->condition(Field::Status, Operator::Equals, Status::Active->value)->create();

    $this->actingAs($user);

    Livewire::test('pages::users')
        ->call('load', $filter->id)
        ->assertSet('loadedFilterId', $filter->id)
        ->call('openDelete', $filter->id)
        ->assertSet('deleteId', $filter->id)
        ->call('deleteFilter')
        ->assertSet('loadedFilterId', null)
        ->assertSet('filter', null);

    expect(SavedFilter::find($filter->id))->toBeNull();
});
