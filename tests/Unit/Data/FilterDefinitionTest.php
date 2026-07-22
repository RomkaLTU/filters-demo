<?php

use App\Data\ConditionData;
use App\Data\FilterDefinition;
use App\Data\GroupData;
use App\Enums\BooleanOperator;
use App\Enums\Country;
use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use App\Exceptions\InvalidFilterDefinition;

describe('rejection classes', function () {
    it('rejects the definition with a positional first-error message', function (array $envelope, string $message) {
        expect(fn () => FilterDefinition::fromEnvelope($envelope))
            ->toThrow(InvalidFilterDefinition::class, $message);
    })->with([
        'unknown field' => [
            fn () => filterEnvelope(filterGroup('and', [
                filterCondition('status', 'equals', 'active'),
                filterGroup('or', [filterCondition('age', 'equals', '5'), filterCondition('name', 'contains', 'x')]),
            ])),
            'Unknown field "age" in group 2, condition 1.',
        ],
        'unknown node type' => [
            fn () => filterEnvelope(filterGroup('and', [['type' => 'banana', 'field' => 'name']])),
            'Unknown node type "banana" in group 1, condition 1.',
        ],
        'operator not allowed for field' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('created_at', 'equals', '2025-01-01')])),
            'Operator "equals" is not valid for field "created_at" in group 1, condition 1.',
        ],
        'invalid boolean' => [
            fn () => filterEnvelope(filterGroup('xor', [filterCondition('name', 'equals', 'x')])),
            'Invalid group operator "xor" in group 1; expected "and" or "or".',
        ],
        'non-member status' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('status', 'equals', 'banana')])),
            '"banana" is not a valid status in group 1, condition 1.',
        ],
        'non-member country' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('country', 'equals', 'ZZ')])),
            '"ZZ" is not a valid country in group 1, condition 1.',
        ],
        'malformed date' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('created_at', 'greater_than', '2025-13-40')])),
            '"2025-13-40" is not a valid date (use YYYY-MM-DD) in group 1, condition 1.',
        ],
        'non-strict date' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('created_at', 'greater_than', '2025-1-1')])),
            '"2025-1-1" is not a valid date (use YYYY-MM-DD) in group 1, condition 1.',
        ],
        'blank value' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('name', 'equals', '   ')])),
            'Missing value in group 1, condition 1.',
        ],
        'empty group' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('name', 'equals', 'x'), filterGroup('or', [])])),
            'Group 2 must contain at least one condition.',
        ],
        'empty root' => [
            fn () => filterEnvelope(filterGroup('and', [])),
            'Group 1 must contain at least one condition.',
        ],
        'root is not a group' => [
            fn () => filterEnvelope(filterCondition('name', 'equals', 'x')),
            'The filter root must be a group.',
        ],
        'missing version' => [
            fn () => ['root' => filterGroup('and', [filterCondition('name', 'equals', 'x')])],
            'This filter uses an unsupported format version; expected version 1.',
        ],
        'wrong version' => [
            fn () => filterEnvelope(filterGroup('and', [filterCondition('name', 'equals', 'x')]), 2),
            'This filter uses an unsupported format version; expected version 1.',
        ],
        'over depth cap' => [
            fn () => filterEnvelope(filterGroup('and', [filterGroup('or', [filterGroup('and', [filterGroup('or', [filterCondition('name', 'equals', 'x')])])])])),
            'This filter is nested too deeply; groups may be nested at most 3 levels.',
        ],
        'over condition cap' => [
            fn () => filterEnvelope(filterGroup('and', array_map(
                fn (int $i) => filterCondition('name', 'equals', "x{$i}"),
                range(1, 51),
            ))),
            'This filter has too many conditions; the maximum is 50.',
        ],
    ]);
});

it('accepts and types a valid nested definition', function () {
    $root = FilterDefinition::fromEnvelope(filterEnvelope(filterGroup('and', [
        filterCondition('status', 'equals', 'active'),
        filterGroup('or', [
            filterCondition('country', 'equals', 'US'),
            filterCondition('name', 'contains', 'ann'),
        ]),
    ])));

    expect($root)->toBeInstanceOf(GroupData::class)
        ->and($root->boolean)->toBe(BooleanOperator::And)
        ->and($root->children)->toHaveCount(2);

    [$status, $nested] = $root->children;

    expect($status)->toBeInstanceOf(ConditionData::class)
        ->and($status->field)->toBe(Field::Status)
        ->and($status->operator)->toBe(Operator::Equals)
        ->and($status->value)->toBe('active')
        ->and($nested)->toBeInstanceOf(GroupData::class)
        ->and($nested->boolean)->toBe(BooleanOperator::Or)
        ->and($nested->children)->toHaveCount(2);
});

it('canonicalises enum values before validating and persisting', function () {
    $root = FilterDefinition::fromEnvelope(filterEnvelope(filterGroup('and', [
        filterCondition('country', 'equals', 'us'),
        filterCondition('status', 'equals', 'ACTIVE'),
        filterCondition('name', 'contains', '  ann  '),
    ])));

    expect($root->children[0]->value)->toBe('US')
        ->and($root->children[0]->field)->toBe(Field::Country)
        ->and($root->children[1]->value)->toBe('active')
        ->and($root->children[2]->value)->toBe('ann');
});

it('ignores unknown keys, including export metadata and node ids', function () {
    $root = FilterDefinition::fromEnvelope([
        'version' => 1,
        'name' => 'Active gmail users',
        'exported_at' => '2026-07-22T00:00:00Z',
        'root' => [
            'type' => 'group',
            'boolean' => 'and',
            'id' => 'node-root',
            'children' => [
                ['type' => 'condition', 'field' => 'email', 'operator' => 'contains', 'value' => 'gmail.com', 'id' => 'node-1'],
            ],
        ],
    ]);

    expect($root)->toBeInstanceOf(GroupData::class)
        ->and($root->children)->toHaveCount(1)
        ->and($root->children[0]->value)->toBe('gmail.com');
});

it('round-trips a definition through the canonical envelope', function () {
    $envelope = filterEnvelope(filterGroup('and', [
        filterCondition('email', 'contains', 'gmail.com'),
        filterGroup('or', [
            filterCondition('country', 'equals', 'US'),
            filterCondition('status', 'not_equals', 'suspended'),
        ]),
    ]));

    $root = FilterDefinition::fromEnvelope($envelope);
    $serialized = FilterDefinition::toEnvelope($root);

    expect($serialized)->toBe($envelope)
        ->and(FilterDefinition::toEnvelope(FilterDefinition::fromEnvelope($serialized)))->toBe($serialized);
});

it('validates a bare normalized root via fromRoot', function () {
    $root = FilterDefinition::fromRoot(filterGroup('or', [filterCondition('status', 'equals', 'pending')]));

    expect($root)->toBeInstanceOf(GroupData::class)
        ->and($root->children[0]->value)->toBe('pending');
});

it('accepts every field with each of its allowed operators', function (Field $field, Operator $operator) {
    $value = match ($field) {
        Field::Status => Status::Active->value,
        Field::Country => Country::US->value,
        Field::CreatedAt => '2025-01-01',
        default => 'sample',
    };

    $root = FilterDefinition::fromRoot(filterGroup('and', [filterCondition($field->value, $operator->value, $value)]));

    expect($root->children[0])->toBeInstanceOf(ConditionData::class)
        ->and($root->children[0]->operator)->toBe($operator);
})->with(function () {
    foreach (Field::cases() as $field) {
        foreach ($field->operators() as $operator) {
            yield "{$field->value} {$operator->value}" => [$field, $operator];
        }
    }
});
