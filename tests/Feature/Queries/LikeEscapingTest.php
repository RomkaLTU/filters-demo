<?php

use App\Models\User;

beforeEach(function () {
    User::factory()->create(['name' => 'Fifty% Off', 'email' => 'percent@example.com']);
    User::factory()->create(['name' => 'FiftyX Off', 'email' => 'decoy-percent@example.com']);
    User::factory()->create(['name' => 'a_b marker', 'email' => 'underscore@example.com']);
    User::factory()->create(['name' => 'axb marker', 'email' => 'decoy-underscore@example.com']);
    User::factory()->create(['name' => 'back\\slash', 'email' => 'backslash@example.com']);
    User::factory()->create(['name' => 'backXslash', 'email' => 'decoy-backslash@example.com']);
});

it('treats % as a literal, not a wildcard, in contains', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('name', 'contains', 'Fifty%'))))
        ->toBe(['percent@example.com']);
});

it('treats _ as a literal, not a single-char wildcard, in contains', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('name', 'contains', 'a_b'))))
        ->toBe(['underscore@example.com']);
});

it('treats a backslash as a literal in contains', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('name', 'contains', 'back\\slash'))))
        ->toBe(['backslash@example.com']);
});

it('escapes metacharacters in equals so the match is exact', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('name', 'equals', 'a_b marker'))))
        ->toBe(['underscore@example.com']);
});

it('escapes metacharacters in starts_with', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('name', 'starts_with', 'Fifty%'))))
        ->toBe(['percent@example.com']);
});
