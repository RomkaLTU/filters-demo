<?php

use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a nested filter, applies it, saves it, then re-executes it from the ?filter URL', function () {
    $owner = User::factory()->create(['name' => 'Olivia Owner', 'email' => 'owner@example.com']);

    User::factory()->active()->create(['name' => 'Alice Anderson', 'email' => 'alice@example.com']);
    User::factory()->active()->create(['name' => 'Bob Brown', 'email' => 'bob@example.com']);
    User::factory()->pending()->create(['name' => 'Mallory Meyer', 'email' => 'mallory@example.com']);

    $this->actingAs($owner);

    $page = visit('/users');

    // Root condition: status equals active. Changing the field morphs the value
    // editor into a status select, so wait for the morph before choosing a value.
    $page->select('[name="draft.children.0.field"]', 'status')
        ->wait(1)
        ->select('[name="draft.children.0.value"]', 'active');

    // Nest an OR group whose single condition is: name contains "Alice".
    $page->click('Add group')
        ->select('[name="draft.children.1.children.0.operator"]', 'contains')
        ->fill('[name="draft.children.1.children.0.value"]', 'Alice')
        ->wait(1); // let the debounced text binding commit before applying

    $page->click('Apply filters')
        ->wait(1)
        ->assertSee('Alice Anderson')
        ->assertDontSee('Bob Brown')
        ->assertDontSee('Mallory Meyer');

    // Save the applied draft as a new filter; the clean URL gains ?filter={id}.
    $page->click('Save filter…')
        ->wait(1)
        ->assertSee('Saved filters appear in the saved-filters menu in the toolbar.')
        ->fill('[name="saveName"]', 'Active Alices')
        ->keys('[name="saveName"]', ['Enter'])
        ->wait(1)
        ->assertSee('Active Alices') // the loaded-filter badge appears once the save round-trip lands
        ->assertQueryStringHas('filter');

    $filter = SavedFilter::query()->where('name', 'Active Alices')->sole();
    expect($filter->user_id)->toBe($owner->id)
        ->and($filter->definition['root']['children'][1]['children'][0]['value'])->toBe('Alice');

    // A fresh load of the ?filter URL re-executes the stored definition.
    visit('/users?filter='.$filter->id)
        ->assertSee('Active Alices')
        ->assertSee('Alice Anderson')
        ->assertDontSee('Bob Brown')
        ->assertDontSee('Mallory Meyer');
});
