<?php

namespace App\Queries;

use App\Data\ConditionData;
use App\Data\FilterDefinition;
use App\Data\FilterNodeData;
use App\Data\GroupData;
use App\Enums\BooleanOperator;
use App\Enums\Operator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * The one custom Spatie filter: it turns the encoded Definition tree into nested
 * SQL. The value arrives as base64url(JSON); it is re-hydrated through the same
 * {@see FilterDefinition} boundary that guards every other entry point (defense
 * in depth), then mapped Group-by-Group to `where`/`orWhere` closures — a direct
 * tree-to-parentheses translation. Operator specifics live in {@see Operator::apply()}.
 *
 * @implements Filter<User>
 */
final class FiltersDefinitionTree implements Filter
{
    /**
     * Encode a validated `{version, root}` envelope for transport through the
     * synthetic query-string, dodging Spatie's comma-splitting and boolean
     * coercion.
     *
     * @param  array{version: int, root: array<string, mixed>}  $envelope
     */
    public static function encode(array $envelope): string
    {
        $json = json_encode($envelope, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (! is_string($value)) {
            return;
        }

        $root = FilterDefinition::fromEnvelope(self::decode($value));

        $query->where(fn (Builder $nested) => $this->applyGroup($nested, $root));
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $value): array
    {
        $base64 = strtr($value, '-_', '+/');
        $padded = str_pad($base64, intdiv(strlen($base64) + 3, 4) * 4, '=');
        $json = base64_decode($padded, strict: true);

        $decoded = $json === false ? null : json_decode($json, associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyGroup(Builder $query, GroupData $group): void
    {
        foreach ($group->children as $child) {
            $constraint = fn (Builder $nested) => $this->applyNode($nested, $child);

            $group->boolean === BooleanOperator::Or
                ? $query->orWhere($constraint)
                : $query->where($constraint);
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyNode(Builder $query, FilterNodeData $node): void
    {
        if ($node instanceof GroupData) {
            $this->applyGroup($query, $node);

            return;
        }

        if ($node instanceof ConditionData) {
            $node->operator->apply($query, $node->field, $node->value);
        }
    }
}
