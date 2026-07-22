<?php

use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows a shared filter read-only to another user, who forks their own private copy', function () {
    $owner = User::factory()->create(['name' => 'Odette Owner', 'email' => 'odette@example.com']);
    $viewer = User::factory()->create(['name' => 'Victor Viewer', 'email' => 'victor@example.com']);

    User::factory()->active()->create(['name' => 'Active Amy', 'email' => 'amy@example.com']);
    User::factory()->suspended()->create(['name' => 'Suspended Sam', 'email' => 'sam@example.com']);

    $shared = SavedFilter::factory()->ownedBy($owner)->shared()
        ->condition(Field::Status, Operator::Equals, Status::Active->value)
        ->create(['name' => 'Team segment']);

    $this->actingAs($viewer);

    // Arriving on the shared filter's link renders the builder read-only and
    // already-applied.
    $page = visit('/users?filter='.$shared->id);

    $page->assertSee('Shared filter — read-only')
        ->assertDisabled('[name="draft.children.0.field"]')
        ->assertSee('Active Amy')
        ->assertDontSee('Suspended Sam');

    // Forking opens the save dialog with the "(mine)" name prefilled.
    $page->click('.ms-auto [wire\:click="openFork"]')
        ->wait(1)
        ->assertSee('Saved filters appear in the saved-filters menu in the toolbar.') // fork dialog is open
        ->assertValue('[name="saveName"]', 'Team segment (mine)')
        ->keys('[name="saveName"]', ['Enter'])
        ->wait(1);

    // The viewer now owns a private copy and the builder is no longer read-only.
    $page->assertSee('Team segment (mine)')
        ->assertDontSee('Shared filter — read-only');

    expect(
        $viewer->savedFilters()
            ->where('name', 'Team segment (mine)')
            ->where('is_shared', false)
            ->exists()
    )->toBeTrue();
});
