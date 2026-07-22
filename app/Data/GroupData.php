<?php

namespace App\Data;

use App\Enums\BooleanOperator;
use Spatie\LaravelData\Attributes\MergeValidationRules;

/**
 * A node combining its children with a single {@see BooleanOperator}. Children
 * are further nodes — conditions or groups — making the tree recursive.
 *
 * A canonical group is never empty, so validation requires at least one child.
 */
#[MergeValidationRules]
final class GroupData extends FilterNodeData
{
    /**
     * @param  array<int, FilterNodeData>  $children
     */
    public function __construct(
        public readonly BooleanOperator $boolean,
        /** @var array<int, FilterNodeData> */
        public readonly array $children,
    ) {
        parent::__construct('group');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'children' => ['array', 'min:1'],
        ];
    }
}
