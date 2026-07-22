<?php

use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $overrides
 */
function insertRawUser(array $overrides = []): void
{
    DB::table('users')->insert(array_merge([
        'name' => 'Raw User',
        'email' => 'raw'.fake()->unique()->numberBetween(1, PHP_INT_MAX).'@example.com',
        'password' => 'x',
    ], $overrides));
}

it('rejects a status outside the allowed set', function () {
    expect(fn () => insertRawUser(['status' => 'archived']))->toThrow(QueryException::class);
});

it('rejects a malformed country', function (string $country) {
    expect(fn () => insertRawUser(['country' => $country]))->toThrow(QueryException::class);
})->with([
    'lowercase' => 'de',
    'three letters' => 'DEU',
    'single letter' => 'D',
    'digits' => '12',
]);

it('accepts a valid country code and null', function () {
    insertRawUser(['country' => 'DE']);
    insertRawUser(['country' => null]);

    expect(DB::table('users')->where('country', 'DE')->count())->toBe(1)
        ->and(DB::table('users')->whereNull('country')->count())->toBe(1);
});

it('rejects a definition that is not a JSON object', function () {
    expect(fn () => SavedFilter::factory()->create(['definition' => []]))
        ->toThrow(QueryException::class);
});

it('enforces case-insensitive name uniqueness per owner', function () {
    $user = User::factory()->create();
    SavedFilter::factory()->ownedBy($user)->create(['name' => 'My Filter']);

    expect(fn () => SavedFilter::factory()->ownedBy($user)->create(['name' => 'MY FILTER']))
        ->toThrow(QueryException::class);
});

it('allows the same name across different owners', function () {
    SavedFilter::factory()->ownedBy(User::factory()->create())->create(['name' => 'Shared Name']);
    SavedFilter::factory()->ownedBy(User::factory()->create())->create(['name' => 'Shared Name']);

    expect(SavedFilter::query()->where('name', 'Shared Name')->count())->toBe(2);
});

it('cascades deletes from a user to filters and recents', function () {
    $user = User::factory()->create();
    $filter = SavedFilter::factory()->ownedBy($user)->create();
    $user->recentFilters()->attach($filter->id, ['last_used_at' => now()]);

    $user->delete();

    expect(SavedFilter::query()->count())->toBe(0)
        ->and(DB::table('saved_filter_user')->count())->toBe(0);
});
