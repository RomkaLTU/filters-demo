<?php

namespace App\Data;

use App\Enums\Field;
use App\Exceptions\InvalidFilterDefinition;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The single trusted boundary for filter Definitions.
 *
 * Untrusted shapes — an imported file, a stored definition, a normalized draft
 * on Apply — enter here and leave as a validated {@see GroupData} tree or an
 * {@see InvalidFilterDefinition} carrying one positionally-worded first error.
 * Values are canonicalised (trimmed, enums cased) before validation so what is
 * returned, and later persisted, is always canonical.
 */
final class FilterDefinition
{
    public const int VERSION = 1;

    /** Maximum number of group levels, including the root. */
    public const int MAX_DEPTH = 3;

    /** Maximum number of conditions across the whole tree. */
    public const int MAX_CONDITIONS = 50;

    /**
     * Validate a full `{version, root}` envelope — the import and stored-filter
     * boundary. Extra keys (e.g. export `name`/`exported_at`) are ignored.
     *
     * @param  array<string, mixed>  $envelope
     */
    public static function fromEnvelope(array $envelope): GroupData
    {
        if (($envelope['version'] ?? null) !== self::VERSION) {
            throw new InvalidFilterDefinition('This filter uses an unsupported format version; expected version 1.');
        }

        $root = $envelope['root'] ?? null;

        if (! is_array($root)) {
            throw new InvalidFilterDefinition('The filter is missing its root group.');
        }

        return self::fromRoot($root);
    }

    /**
     * Validate a bare root group — the Apply boundary, where the draft has
     * already been normalized to a canonical shape.
     *
     * @param  array<string, mixed>  $root
     */
    public static function fromRoot(array $root): GroupData
    {
        $root = self::canonicalize($root);

        if (($root['type'] ?? null) !== 'group') {
            throw new InvalidFilterDefinition('The filter root must be a group.');
        }

        self::assertWithinCaps($root);

        try {
            $node = FilterNodeData::validateAndCreate($root);
        } catch (ValidationException $exception) {
            throw new InvalidFilterDefinition(self::firstError($exception, $root));
        }

        if (! $node instanceof GroupData) {
            throw new InvalidFilterDefinition('The filter root must be a group.');
        }

        return $node;
    }

    /**
     * Serialize a validated tree back to the canonical `{version, root}`
     * envelope stored in the database, encoded into the pipeline, and exported.
     *
     * @return array{version: int, root: array<string, mixed>}
     */
    public static function toEnvelope(GroupData $root): array
    {
        return ['version' => self::VERSION, 'root' => self::serialize($root)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function serialize(FilterNodeData $node): array
    {
        if ($node instanceof ConditionData) {
            return [
                'type' => 'condition',
                'field' => $node->field->value,
                'operator' => $node->operator->value,
                'value' => $node->value,
            ];
        }

        /** @var GroupData $node */
        return [
            'type' => 'group',
            'boolean' => $node->boolean->value,
            'children' => array_map(self::serialize(...), $node->children),
        ];
    }

    /**
     * Trim every value, lowercase status and uppercase country, so membership
     * checks see — and the DTO persists — canonical enum values.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function canonicalize(array $node): array
    {
        if (($node['type'] ?? null) === 'condition') {
            if (is_string($node['value'] ?? null)) {
                $field = is_string($node['field'] ?? null) ? Field::tryFrom($node['field']) : null;
                $node['value'] = $field?->canonicalize($node['value']) ?? trim($node['value']);
            }

            return $node;
        }

        if (is_array($node['children'] ?? null)) {
            $node['children'] = array_map(
                fn (mixed $child): mixed => is_array($child) ? self::canonicalize($child) : $child,
                $node['children'],
            );
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $root
     */
    private static function assertWithinCaps(array $root): void
    {
        if (self::depth($root) > self::MAX_DEPTH) {
            throw new InvalidFilterDefinition(
                sprintf('This filter is nested too deeply; groups may be nested at most %d levels.', self::MAX_DEPTH),
            );
        }

        if (self::countConditions($root) > self::MAX_CONDITIONS) {
            throw new InvalidFilterDefinition(
                sprintf('This filter has too many conditions; the maximum is %d.', self::MAX_CONDITIONS),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function depth(array $node): int
    {
        if (($node['type'] ?? null) !== 'group') {
            return 0;
        }

        $deepest = 0;

        foreach (self::childEntries($node) as $child) {
            if (is_array($child) && ($child['type'] ?? null) === 'group') {
                $deepest = max($deepest, self::depth($child));
            }
        }

        return 1 + $deepest;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function countConditions(array $node): int
    {
        if (($node['type'] ?? null) === 'condition') {
            return 1;
        }

        $total = 0;

        foreach (self::childEntries($node) as $child) {
            if (is_array($child)) {
                $total += self::countConditions($child);
            }
        }

        return $total;
    }

    /**
     * Turn a Laravel validation failure into the single, positionally-worded
     * first error, chosen by tree order then by slot within a node.
     *
     * @param  array<string, mixed>  $root
     */
    private static function firstError(ValidationException $exception, array $root): string
    {
        $positions = self::positions($root);
        $paths = array_keys($exception->errors());

        usort($paths, fn (string $a, string $b): int => self::rank($a, $positions) <=> self::rank($b, $positions));

        return self::messageFor($paths[0], $root, $positions);
    }

    /**
     * Number every node the way a reader would: groups counted in pre-order
     * (root is 1), conditions counted within their own group.
     *
     * @param  array<string, mixed>  $root
     * @return array<string, array{group: int, condition: int|null, order: int}>
     */
    private static function positions(array $root): array
    {
        $positions = [];
        $groupNumber = 0;
        $order = 0;

        $walk = function (array $group, string $path) use (&$walk, &$positions, &$groupNumber, &$order): void {
            $groupNumber++;
            $ownGroup = $groupNumber;
            $positions[$path] = ['group' => $ownGroup, 'condition' => null, 'order' => $order++];

            $conditionNumber = 0;

            foreach (self::childEntries($group) as $index => $child) {
                $childPath = $path === '' ? "children.{$index}" : "{$path}.children.{$index}";

                if (is_array($child) && ($child['type'] ?? null) === 'group') {
                    $walk($child, $childPath);

                    continue;
                }

                $conditionNumber++;
                $positions[$childPath] = ['group' => $ownGroup, 'condition' => $conditionNumber, 'order' => $order++];
            }
        };

        $walk($root, '');

        return $positions;
    }

    /**
     * @param  array<string, array{group: int, condition: int|null, order: int}>  $positions
     * @return array{int, int}
     */
    private static function rank(string $path, array $positions): array
    {
        [$nodePath, $slot] = self::splitPath($path);

        $slotPriority = ['type' => 0, 'boolean' => 1, 'field' => 2, 'operator' => 3, 'value' => 4, 'children' => 5];

        return [$positions[$nodePath]['order'] ?? PHP_INT_MAX, $slotPriority[$slot] ?? 6];
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<string, array{group: int, condition: int|null, order: int}>  $positions
     */
    private static function messageFor(string $path, array $root, array $positions): string
    {
        [$nodePath, $slot] = self::splitPath($path);
        $where = self::where($positions[$nodePath] ?? ['group' => 1, 'condition' => null, 'order' => 0]);
        $at = fn (string $key): mixed => Arr::get($root, $nodePath === '' ? $key : "{$nodePath}.{$key}");

        return match ($slot) {
            'field' => sprintf('Unknown field "%s" in %s.', self::text($at('field')), $where),
            'type' => sprintf('Unknown node type "%s" in %s.', self::text($at('type')), $where),
            'boolean' => sprintf('Invalid group operator "%s" in %s; expected "and" or "or".', self::text($at('boolean')), $where),
            'children' => sprintf('%s must contain at least one condition.', ucfirst($where)),
            'operator' => sprintf('Operator "%s" is not valid for field "%s" in %s.', self::text($at('operator')), self::text($at('field')), $where),
            'value' => self::valueMessage(self::text($at('value')), self::text($at('field')), $where),
            default => sprintf('Invalid filter node in %s.', $where),
        };
    }

    private static function valueMessage(string $value, string $field, string $where): string
    {
        if (trim($value) === '') {
            return sprintf('Missing value in %s.', $where);
        }

        return match (Field::tryFrom($field)) {
            Field::Status => sprintf('"%s" is not a valid status in %s.', $value, $where),
            Field::Country => sprintf('"%s" is not a valid country in %s.', $value, $where),
            Field::CreatedAt => sprintf('"%s" is not a valid date (use YYYY-MM-DD) in %s.', $value, $where),
            default => sprintf('Invalid value "%s" in %s.', $value, $where),
        };
    }

    /**
     * @return array{string, string}
     */
    private static function splitPath(string $path): array
    {
        $segments = explode('.', $path);
        $last = $segments[count($segments) - 1];

        if (in_array($last, ['type', 'field', 'operator', 'value', 'boolean', 'children'], true)) {
            array_pop($segments);

            return [implode('.', $segments), $last];
        }

        return [$path, ''];
    }

    /**
     * @param  array{group: int, condition: int|null, order: int}  $position
     */
    private static function where(array $position): string
    {
        if ($position['condition'] !== null) {
            return sprintf('group %d, condition %d', $position['group'], $position['condition']);
        }

        return sprintf('group %d', $position['group']);
    }

    /**
     * The raw children entries of a node, keys preserved so they line up with
     * Laravel's dotted error paths. Callers guard non-array entries themselves.
     *
     * @param  array<string, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function childEntries(array $node): array
    {
        $children = $node['children'] ?? null;

        return is_array($children) ? $children : [];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : gettype($value);
    }
}
