<?php

use App\Data\FilterDefinition;
use App\Enums\Status;
use App\Exceptions\InvalidFilterDefinition;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed();
});

test('it seeds exactly 10,000 users with an active-weighted distribution', function () {
    expect(User::count())->toBe(10_000)
        ->and(User::where('status', Status::Active)->count())->toBeGreaterThan(6_500)
        ->and(User::whereNull('country')->count())->toBeGreaterThan(0);

    foreach (Status::cases() as $status) {
        expect(User::where('status', $status)->count())->toBeGreaterThan(0);
    }
});

test('it seeds the two demo accounts, both usable with password "password"', function () {
    $demo = User::where('email', 'demo@example.com')->firstOrFail();
    $colleague = User::where('email', 'colleague@example.com')->firstOrFail();

    expect($demo->status)->toBe(Status::Active)
        ->and($colleague->status)->toBe(Status::Active)
        ->and(Hash::check('password', $demo->password))->toBeTrue()
        ->and(Hash::check('password', $colleague->password))->toBeTrue();
});

test('it seeds the seven-filter roster with the right ownership and sharing', function () {
    $demo = User::where('email', 'demo@example.com')->firstOrFail();
    $colleague = User::where('email', 'colleague@example.com')->firstOrFail();

    expect(SavedFilter::count())->toBe(7)
        ->and($demo->savedFilters()->pluck('name')->sort()->values()->all())->toBe([
            'Active users', 'Names containing \'ann\'', 'Q1 legacy segment', 'Signups needing attention',
        ])
        ->and($colleague->savedFilters()->pluck('name')->sort()->values()->all())->toBe([
            'Colleague\'s private notes', 'Recently joined Europeans', 'Suspended accounts',
        ])
        ->and(SavedFilter::where('is_shared', true)->pluck('name')->sort()->values()->all())->toBe([
            'Recently joined Europeans', 'Suspended accounts',
        ]);
});

test('the stale filter fails the DTO boundary while the rest validate', function () {
    $demo = User::where('email', 'demo@example.com')->firstOrFail();

    $stale = SavedFilter::where('name', 'Q1 legacy segment')->firstOrFail();
    expect(fn () => FilterDefinition::fromEnvelope($stale->definition))
        ->toThrow(InvalidFilterDefinition::class);

    $valid = SavedFilter::whereNot('name', 'Q1 legacy segment')->get();
    foreach ($valid as $filter) {
        expect(FilterDefinition::fromEnvelope($filter->definition))->not->toBeNull();
    }
});

test('it scopes colleague private filters away from demo', function () {
    $demo = User::where('email', 'demo@example.com')->firstOrFail();

    $visible = SavedFilter::visibleTo($demo)->pluck('name')->all();

    expect($visible)->toContain('Recently joined Europeans')
        ->and($visible)->toContain('Suspended accounts')
        ->and($visible)->not->toContain('Colleague\'s private notes');
});

test('re-seeding is idempotent and leaves the dataset unchanged', function () {
    seed();
    seed();

    expect(User::count())->toBe(10_000)
        ->and(SavedFilter::count())->toBe(7)
        ->and(DB::table('saved_filter_user')->count())->toBe(2);
});

test('it pre-warms demo recents to 2-of-3 with fixed timestamps', function () {
    $demo = User::where('email', 'demo@example.com')->firstOrFail();

    expect(DB::table('saved_filter_user')->count())->toBe(2);

    $recents = $demo->recentFilters()
        ->orderByPivot('last_used_at', 'desc')
        ->pluck('name')
        ->all();

    expect($recents)->toBe(['Recently joined Europeans', 'Active users']);
});
