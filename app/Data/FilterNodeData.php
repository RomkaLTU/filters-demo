<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\PropertyForMorph;
use Spatie\LaravelData\Contracts\PropertyMorphableData;
use Spatie\LaravelData\Data;

/**
 * A node in a filter Definition tree, discriminated by its {@see $type}.
 *
 * This abstract class is the single boundary through which untrusted filter
 * shapes enter the domain: {@see self::validateAndCreate()} morphs each node to
 * its concrete {@see ConditionData} or {@see GroupData} and validates it.
 */
abstract class FilterNodeData extends Data implements PropertyMorphableData
{
    public function __construct(
        #[PropertyForMorph]
        public readonly string $type,
    ) {}

    /**
     * @param  array{type?: string}  $properties
     */
    public static function morph(array $properties): ?string
    {
        return match ($properties['type'] ?? null) {
            'condition' => ConditionData::class,
            'group' => GroupData::class,
            default => null,
        };
    }
}
