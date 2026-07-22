<?php

namespace App\Data;

use App\Enums\Field;

/**
 * Turns a Draft into a Canonical Definition.
 *
 * Blank conditions are pruned first, then groups they emptied, bottom-up. Values
 * are canonicalised and ephemeral node ids dropped, so the result matches the
 * shape {@see FilterDefinition::toEnvelope()} persists — which is what dirty
 * comparison (normalize-then-compare) relies on. A root that prunes to nothing
 * yields `null`: no Definition, an unfiltered table, nothing to save.
 */
final class FilterTreeNormalizer
{
    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>|null
     */
    public static function normalize(array $draft): ?array
    {
        return self::node($draft);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function node(array $node): ?array
    {
        return match ($node['type'] ?? null) {
            'condition' => self::condition($node),
            'group' => self::group($node),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function condition(array $node): ?array
    {
        $value = is_string($node['value'] ?? null) ? trim($node['value']) : '';

        if ($value === '') {
            return null;
        }

        $field = is_string($node['field'] ?? null) ? Field::tryFrom($node['field']) : null;

        return [
            'type' => 'condition',
            'field' => $node['field'] ?? null,
            'operator' => $node['operator'] ?? null,
            'value' => $field?->canonicalize($value) ?? $value,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function group(array $node): ?array
    {
        $children = [];

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            $normalized = is_array($child) ? self::node($child) : null;

            if ($normalized !== null) {
                $children[] = $normalized;
            }
        }

        if ($children === []) {
            return null;
        }

        return [
            'type' => 'group',
            'boolean' => $node['boolean'] ?? null,
            'children' => $children,
        ];
    }
}
