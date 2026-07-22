<?php

use App\Data\FilterDefinition;
use App\Enums\Status;
use App\Exceptions\InvalidFilterDefinition;
use App\Models\User;

beforeEach(function () {
    User::factory()->create(['email' => 'pending-de-2025@example.com', 'status' => Status::Pending, 'country' => 'DE', 'created_at' => '2025-06-01 12:00:00']);
    User::factory()->create(['email' => 'suspended-fr-2025@example.com', 'status' => Status::Suspended, 'country' => 'FR', 'created_at' => '2025-06-01 12:00:00']);
    User::factory()->create(['email' => 'pending-de-2024@example.com', 'status' => Status::Pending, 'country' => 'DE', 'created_at' => '2024-06-01 12:00:00']);
    User::factory()->create(['email' => 'pending-us-2025@example.com', 'status' => Status::Pending, 'country' => 'US', 'created_at' => '2025-06-01 12:00:00']);
    User::factory()->create(['email' => 'active-de-2025@example.com', 'status' => Status::Active, 'country' => 'DE', 'created_at' => '2025-06-01 12:00:00']);
});

it('maps a depth-3 tree to the correct nested AND/OR result', function () {
    $root = filterGroup('and', [
        filterGroup('or', [
            filterCondition('status', 'equals', 'pending'),
            filterCondition('status', 'equals', 'suspended'),
        ]),
        filterGroup('or', [
            filterCondition('country', 'equals', 'DE'),
            filterCondition('country', 'equals', 'FR'),
        ]),
        filterCondition('created_at', 'greater_than', '2025-01-01'),
    ]);

    expect(selectedEmails(filteredUsers($root)))
        ->toBe(['pending-de-2025@example.com', 'suspended-fr-2025@example.com']);
});

it('preserves group parentheses rather than flattening precedence', function () {
    $root = filterGroup('and', [
        filterCondition('status', 'equals', 'pending'),
        filterGroup('or', [
            filterCondition('country', 'equals', 'US'),
            filterCondition('country', 'equals', 'DE'),
        ]),
    ]);

    expect(selectedEmails(filteredUsers($root)))
        ->toBe(['pending-de-2024@example.com', 'pending-de-2025@example.com', 'pending-us-2025@example.com']);
});

it('prunes a blank condition and keeps its siblings', function () {
    $draft = filterGroup('or', [
        filterCondition('country', 'equals', '', 'node-blank'),
        filterCondition('country', 'equals', 'US', 'node-us'),
    ], 'node-root');

    expect(selectedEmails(filteredUsersFromDraft($draft)))
        ->toBe(['pending-us-2025@example.com']);
});

it('prunes a group that its blank conditions emptied', function () {
    $draft = filterGroup('and', [
        filterCondition('country', 'equals', 'US'),
        filterGroup('or', [
            filterCondition('name', 'contains', ''),
            filterCondition('name', 'starts_with', ''),
        ]),
    ]);

    expect(selectedEmails(filteredUsersFromDraft($draft)))
        ->toBe(['pending-us-2025@example.com']);
});

it('leaves the table unfiltered when the root prunes to nothing', function () {
    $draft = filterGroup('and', [filterCondition('name', 'equals', '   ')]);

    expect(filteredUsersFromDraft($draft)->count())->toBe(User::query()->count());
});

it('rejects a tree nested deeper than three groups at the boundary', function () {
    $root = filterGroup('and', [
        filterGroup('or', [
            filterGroup('and', [
                filterGroup('or', [filterCondition('name', 'equals', 'x')]),
            ]),
        ]),
    ]);

    expect(fn () => FilterDefinition::fromRoot($root))
        ->toThrow(InvalidFilterDefinition::class, 'This filter is nested too deeply; groups may be nested at most 3 levels.');
});

it('rejects a tree with more than fifty conditions at the boundary', function () {
    $root = filterGroup('and', array_map(
        fn (int $i) => filterCondition('name', 'equals', "x{$i}"),
        range(1, 51),
    ));

    expect(fn () => FilterDefinition::fromRoot($root))
        ->toThrow(InvalidFilterDefinition::class, 'This filter has too many conditions; the maximum is 50.');
});
