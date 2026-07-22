<?php

use App\Data\FilterDefinition;
use App\Data\GroupData;
use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use App\Models\SavedFilter;
use App\Models\User;

it('creates users with a canonical status and country by default', function () {
    $user = User::factory()->create();

    expect($user->status)->toBeInstanceOf(Status::class)
        ->and($user->status)->toBe(Status::Active)
        ->and($user->country)->toMatch('/^[A-Z]{2}$/');
});

it('applies each status state', function (string $method, Status $status) {
    expect(User::factory()->{$method}()->create()->fresh()->status)->toBe($status);
})->with([
    ['active', Status::Active],
    ['pending', Status::Pending],
    ['suspended', Status::Suspended],
    ['deactivated', Status::Deactivated],
]);

it('sets a country, including null', function () {
    expect(User::factory()->country('DE')->create()->fresh()->country)->toBe('DE')
        ->and(User::factory()->country(null)->create()->fresh()->country)->toBeNull();
});

it('places created_at within the requested range', function () {
    $user = User::factory()->createdBetween('2020-01-01', '2020-12-31')->create();

    expect($user->created_at->between('2020-01-01', '2021-01-01'))->toBeTrue();
});

it('creates a saved filter with a ULID key and round-tripping definition', function () {
    $filter = SavedFilter::factory()->create();

    expect($filter->id)->toBeString()->toHaveLength(26)
        ->and($filter->is_shared)->toBeFalse()
        ->and($filter->definition)->toHaveKeys(['version', 'root'])
        ->and($filter->fresh()->definition)->toEqualCanonicalizing($filter->definition);
});

it('produces preset definitions that pass the validation boundary', function (SavedFilter $filter) {
    expect(FilterDefinition::fromEnvelope($filter->definition))->toBeInstanceOf(GroupData::class);
})->with([
    'default' => fn () => SavedFilter::factory()->create(),
    'condition preset' => fn () => SavedFilter::factory()->condition(Field::Name, Operator::Contains, 'ann')->create(),
    'nested preset' => fn () => SavedFilter::factory()->nested()->create(),
]);

it('links owner, ownedBy and shared states', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->shared()->create();

    expect($filter->user_id)->toBe($user->id)
        ->and($filter->is_shared)->toBeTrue()
        ->and($filter->owner->is($user))->toBeTrue()
        ->and($user->savedFilters()->whereKey($filter->id)->exists())->toBeTrue();
});

it('exposes recent filters through the pivot with last_used_at', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->create();

    $user->recentFilters()->attach($filter->id, ['last_used_at' => now()]);

    $recent = $user->recentFilters()->first();
    expect($recent->is($filter))->toBeTrue()
        ->and($recent->pivot->last_used_at)->not->toBeNull();
});

it('scopes visible filters to own plus shared', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $mine = SavedFilter::factory()->ownedBy($me)->create();
    $sharedByOther = SavedFilter::factory()->ownedBy($other)->shared()->create();
    $privateOfOther = SavedFilter::factory()->ownedBy($other)->create();

    $visible = SavedFilter::query()->visibleTo($me)->pluck('id');

    expect($visible)->toContain($mine->id, $sharedByOther->id)
        ->and($visible)->not->toContain($privateOfOther->id);
});
