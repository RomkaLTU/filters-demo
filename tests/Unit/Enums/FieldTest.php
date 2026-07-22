<?php

use App\Enums\Field;
use App\Enums\Operator;

it('exposes the operator subset for each field', function (Field $field, array $expected) {
    expect($field->operators())->toBe($expected);
})->with([
    'name' => [Field::Name, [Operator::Equals, Operator::NotEquals, Operator::Contains, Operator::StartsWith]],
    'email' => [Field::Email, [Operator::Equals, Operator::NotEquals, Operator::Contains, Operator::StartsWith]],
    'status' => [Field::Status, [Operator::Equals, Operator::NotEquals]],
    'country' => [Field::Country, [Operator::Equals, Operator::NotEquals]],
    'created_at' => [Field::CreatedAt, [Operator::GreaterThan, Operator::LessThan]],
]);

it('never allows equals on created_at (the timestamp footgun)', function () {
    expect(Field::CreatedAt->allows(Operator::Equals))->toBeFalse()
        ->and(Field::CreatedAt->allows(Operator::GreaterThan))->toBeTrue();
});

it('canonicalises values by field: trim always, case enums', function (Field $field, string $input, string $expected) {
    expect($field->canonicalize($input))->toBe($expected);
})->with([
    'status lowercased' => [Field::Status, ' ACTIVE ', 'active'],
    'country uppercased' => [Field::Country, ' de ', 'DE'],
    'name trimmed only' => [Field::Name, '  Ann  ', 'Ann'],
    'email trimmed only' => [Field::Email, ' A@B.com ', 'A@B.com'],
    'date trimmed only' => [Field::CreatedAt, ' 2025-01-01 ', '2025-01-01'],
]);
