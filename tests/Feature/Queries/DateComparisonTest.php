<?php

use App\Exceptions\InvalidFilterDefinition;
use App\Models\User;

beforeEach(function () {
    User::factory()->create(['email' => 'dec31@example.com', 'created_at' => '2024-12-31 23:59:00']);
    User::factory()->create(['email' => 'jan01-early@example.com', 'created_at' => '2025-01-01 00:01:00']);
    User::factory()->create(['email' => 'jan01-late@example.com', 'created_at' => '2025-01-01 23:59:00']);
    User::factory()->create(['email' => 'jan02@example.com', 'created_at' => '2025-01-02 00:01:00']);
});

it('excludes the boundary day for greater_than (exclusive bounds)', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('created_at', 'greater_than', '2025-01-01'))))
        ->toBe(['jan02@example.com']);
});

it('excludes the boundary day for less_than (exclusive bounds)', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('created_at', 'less_than', '2025-01-01'))))
        ->toBe(['dec31@example.com']);
});

it('compares at UTC-day granularity, ignoring the time of day', function () {
    expect(selectedEmails(filteredUsers(conditionRoot('created_at', 'greater_than', '2024-12-31'))))
        ->toBe(['jan01-early@example.com', 'jan01-late@example.com', 'jan02@example.com']);
});

it('rejects a non-strict Y-m-d date at the Apply boundary', function () {
    expect(fn () => filteredUsers(conditionRoot('created_at', 'greater_than', '2025-1-1')))
        ->toThrow(InvalidFilterDefinition::class, '"2025-1-1" is not a valid date (use YYYY-MM-DD) in group 1, condition 1.');
});
