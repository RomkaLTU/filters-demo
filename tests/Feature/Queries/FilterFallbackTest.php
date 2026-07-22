<?php

use App\Data\FilterDefinition;
use App\Exceptions\InvalidFilterDefinition;
use App\Models\SavedFilter;
use App\Models\User;
use App\Queries\FilteredUsersQuery;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Resolve the query a viewer gets from a `?filter={id}`, applying the pipeline's
 * failure posture: a filter that is not visible (dead/foreign/unshared) or whose
 * stored definition no longer validates (stale) falls back to unfiltered.
 *
 * @return QueryBuilder<User>
 */
function usersForSavedFilter(User $viewer, string $id): QueryBuilder
{
    $filter = SavedFilter::query()->visibleTo($viewer)->find($id);

    if ($filter === null) {
        return filteredUsers(null);
    }

    try {
        $definition = FilterDefinition::fromEnvelope($filter->definition);
    } catch (InvalidFilterDefinition) {
        return filteredUsers(null);
    }

    return app(FilteredUsersQuery::class)->builder($definition);
}

beforeEach(function () {
    User::factory()->create(['email' => 'us@example.com', 'country' => 'US']);
    User::factory()->create(['email' => 'de@example.com', 'country' => 'DE']);
});

it('applies a visible, valid saved filter through the pipeline', function () {
    $viewer = User::factory()->country(null)->create();
    $filter = SavedFilter::factory()->ownedBy($viewer)
        ->withDefinition(filterEnvelope(conditionRoot('country', 'equals', 'US')))
        ->create();

    expect(selectedEmails(usersForSavedFilter($viewer, $filter->id)))->toBe(['us@example.com']);
});

it('falls back to unfiltered for a stale stored definition and rejects it loudly', function () {
    $viewer = User::factory()->create();
    $staleEnvelope = filterEnvelope(conditionRoot('created_at', 'equals', '2025-01-01'));
    $filter = SavedFilter::factory()->ownedBy($viewer)->withDefinition($staleEnvelope)->create();

    expect(fn () => FilterDefinition::fromEnvelope($filter->definition))
        ->toThrow(InvalidFilterDefinition::class)
        ->and(usersForSavedFilter($viewer, $filter->id)->count())->toBe(User::query()->count());
});

it('falls back to unfiltered for a dead filter id', function () {
    $viewer = User::factory()->create();

    expect(usersForSavedFilter($viewer, (string) Str::ulid())->count())->toBe(User::query()->count());
});

it('falls back to unfiltered for a foreign, unshared filter', function () {
    $viewer = User::factory()->create();
    $foreign = SavedFilter::factory()->ownedBy(User::factory()->create())
        ->withDefinition(filterEnvelope(conditionRoot('country', 'equals', 'US')))
        ->create();

    expect(SavedFilter::query()->visibleTo($viewer)->find($foreign->id))->toBeNull()
        ->and(usersForSavedFilter($viewer, $foreign->id)->count())->toBe(User::query()->count());
});

it('applies another user shared filter but not their private one', function () {
    $viewer = User::factory()->country(null)->create();
    $owner = User::factory()->country(null)->create();
    $shared = SavedFilter::factory()->ownedBy($owner)->shared()
        ->withDefinition(filterEnvelope(conditionRoot('country', 'equals', 'DE')))
        ->create();
    $private = SavedFilter::factory()->ownedBy($owner)
        ->withDefinition(filterEnvelope(conditionRoot('country', 'equals', 'US')))
        ->create();

    expect(selectedEmails(usersForSavedFilter($viewer, $shared->id)))->toBe(['de@example.com'])
        ->and(usersForSavedFilter($viewer, $private->id)->count())->toBe(User::query()->count());
});
