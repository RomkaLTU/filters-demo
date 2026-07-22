<?php

use App\Data\FilterDefinition;
use App\Data\FilterTreeNormalizer;

it('prunes blank conditions and strips ids', function () {
    $normalized = FilterTreeNormalizer::normalize(filterGroup('or', [
        filterCondition('name', 'contains', '', 'blank'),
        filterCondition('country', 'equals', 'US', 'keep'),
    ], 'root'));

    expect($normalized)->toBe([
        'type' => 'group',
        'boolean' => 'or',
        'children' => [
            ['type' => 'condition', 'field' => 'country', 'operator' => 'equals', 'value' => 'US'],
        ],
    ]);
});

it('prunes groups that empty out, bottom-up', function () {
    $normalized = FilterTreeNormalizer::normalize(filterGroup('and', [
        filterCondition('status', 'equals', 'active'),
        filterGroup('or', [
            filterCondition('name', 'contains', '   '),
            filterCondition('email', 'starts_with', ''),
        ], 'empties'),
    ]));

    expect($normalized['children'])->toHaveCount(1)
        ->and($normalized['children'][0]['field'])->toBe('status');
});

it('yields no definition when the root prunes to nothing', function () {
    $normalized = FilterTreeNormalizer::normalize(filterGroup('and', [
        filterCondition('name', 'contains', ''),
        filterGroup('or', [filterCondition('email', 'equals', '  ')]),
    ]));

    expect($normalized)->toBeNull();
});

it('canonicalises values so the result matches the persisted shape', function () {
    $normalized = FilterTreeNormalizer::normalize(filterGroup('and', [
        filterCondition('status', 'equals', 'ACTIVE'),
        filterCondition('country', 'equals', 'de'),
        filterCondition('name', 'contains', '  ann  '),
    ]));

    expect($normalized['children'][0]['value'])->toBe('active')
        ->and($normalized['children'][1]['value'])->toBe('DE')
        ->and($normalized['children'][2]['value'])->toBe('ann');
});

it('produces a canonical tree the boundary accepts unchanged', function () {
    $normalized = FilterTreeNormalizer::normalize(filterGroup('and', [
        filterCondition('email', 'contains', 'gmail.com'),
        filterGroup('or', [
            filterCondition('country', 'equals', 'us'),
            filterCondition('name', 'contains', ''),
        ]),
    ]));

    expect($normalized)->not->toBeNull();

    $root = FilterDefinition::fromRoot($normalized);

    expect(FilterDefinition::toEnvelope($root)['root'])->toBe($normalized);
});
