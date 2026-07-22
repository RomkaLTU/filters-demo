<?php

use App\Enums\Status;
use App\Models\User;

beforeEach(function () {
    User::factory()->create(['name' => 'Alice Anderson', 'email' => 'alice@example.com', 'status' => Status::Active, 'country' => 'US', 'created_at' => '2025-06-15 12:00:00']);
    User::factory()->create(['name' => 'Bob Brown', 'email' => 'bob@sample.org', 'status' => Status::Pending, 'country' => 'DE', 'created_at' => '2024-03-10 12:00:00']);
    User::factory()->create(['name' => 'Carol Clark', 'email' => 'carol@example.com', 'status' => Status::Suspended, 'country' => 'FR', 'created_at' => '2023-11-20 12:00:00']);
    User::factory()->create(['name' => 'Dave Davis', 'email' => 'dave@demo.net', 'status' => Status::Deactivated, 'country' => 'GB', 'created_at' => '2025-01-01 12:00:00']);
    User::factory()->create(['name' => 'Erin Evans', 'email' => 'erin@example.com', 'status' => Status::Active, 'country' => null, 'created_at' => '2025-12-31 12:00:00']);
});

it('selects exactly the expected users for each field and operator', function (array $root, array $expected) {
    expect(selectedEmails(filteredUsers($root)))->toBe(collect($expected)->sort()->values()->all());
})->with([
    'name equals' => [fn () => conditionRoot('name', 'equals', 'Alice Anderson'), ['alice@example.com']],
    'name not_equals' => [fn () => conditionRoot('name', 'not_equals', 'Alice Anderson'), ['bob@sample.org', 'carol@example.com', 'dave@demo.net', 'erin@example.com']],
    'name contains' => [fn () => conditionRoot('name', 'contains', 'nde'), ['alice@example.com']],
    'name starts_with' => [fn () => conditionRoot('name', 'starts_with', 'Bob'), ['bob@sample.org']],

    'email equals' => [fn () => conditionRoot('email', 'equals', 'alice@example.com'), ['alice@example.com']],
    'email not_equals' => [fn () => conditionRoot('email', 'not_equals', 'alice@example.com'), ['bob@sample.org', 'carol@example.com', 'dave@demo.net', 'erin@example.com']],
    'email contains' => [fn () => conditionRoot('email', 'contains', 'example.com'), ['alice@example.com', 'carol@example.com', 'erin@example.com']],
    'email starts_with' => [fn () => conditionRoot('email', 'starts_with', 'bob@'), ['bob@sample.org']],

    'status equals' => [fn () => conditionRoot('status', 'equals', 'active'), ['alice@example.com', 'erin@example.com']],
    'status not_equals' => [fn () => conditionRoot('status', 'not_equals', 'active'), ['bob@sample.org', 'carol@example.com', 'dave@demo.net']],

    'country equals' => [fn () => conditionRoot('country', 'equals', 'US'), ['alice@example.com']],
    'country not_equals' => [fn () => conditionRoot('country', 'not_equals', 'US'), ['bob@sample.org', 'carol@example.com', 'dave@demo.net']],

    'created_at greater_than' => [fn () => conditionRoot('created_at', 'greater_than', '2025-01-01'), ['alice@example.com', 'erin@example.com']],
    'created_at less_than' => [fn () => conditionRoot('created_at', 'less_than', '2025-01-01'), ['bob@sample.org', 'carol@example.com']],
]);

it('matches text operators case-insensitively', function (array $root, array $expected) {
    expect(selectedEmails(filteredUsers($root)))->toBe(collect($expected)->sort()->values()->all());
})->with([
    'equals ignores case' => [fn () => conditionRoot('name', 'equals', 'alice anderson'), ['alice@example.com']],
    'not_equals ignores case' => [fn () => conditionRoot('name', 'not_equals', 'ALICE ANDERSON'), ['bob@sample.org', 'carol@example.com', 'dave@demo.net', 'erin@example.com']],
    'contains ignores case' => [fn () => conditionRoot('name', 'contains', 'NDE'), ['alice@example.com']],
    'starts_with ignores case' => [fn () => conditionRoot('email', 'starts_with', 'BOB@'), ['bob@sample.org']],
]);
