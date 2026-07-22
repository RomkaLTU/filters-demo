<?php

use App\Data\FilterDefinition;
use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('imports an exported filter file, landing it as an unsaved draft', function () {
    $user = User::factory()->create(['name' => 'Uma User', 'email' => 'uma@example.com']);

    User::factory()->active()->create(['name' => 'Ada Active', 'email' => 'ada@example.com']);
    User::factory()->pending()->create(['name' => 'Peter Pending', 'email' => 'peter@example.com']);

    $filter = SavedFilter::factory()->ownedBy($user)
        ->condition(Field::Status, Operator::Equals, Status::Active->value)
        ->create(['name' => 'Exported segment']);

    // Reproduce exactly what the export action streams to disk: the canonical
    // envelope plus the transient name/exported_at metadata.
    $definition = FilterDefinition::fromEnvelope($filter->definition);
    $payload = FilterDefinition::toEnvelope($definition);
    $payload['name'] = $filter->name;
    $payload['exported_at'] = now()->toIso8601String();
    $exportedJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $this->actingAs($user);

    $page = visit('/users');

    $page->click('Import')
        ->wait(1)
        ->assertSee('Drop a JSON file or paste exported filter JSON below.')
        ->fill('[name="importText"]', $exportedJson)
        ->click('[wire\:click="import"]')
        ->wait(1);

    // The imported definition filters the table, but nothing is loaded or saved:
    // no filter badge, no ?filter on the URL — an unsaved draft.
    $page->assertSee('Ada Active')
        ->assertDontSee('Peter Pending')
        ->assertDontSee('Exported segment')
        ->assertQueryStringMissing('filter');
});
