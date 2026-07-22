<?php

namespace App\Enums;

use Illuminate\Database\Eloquent\Builder;

enum Operator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';

    /**
     * The human label shown in the builder's operator select.
     */
    public function label(): string
    {
        return match ($this) {
            self::Equals => 'equals',
            self::NotEquals => 'not equals',
            self::Contains => 'contains',
            self::StartsWith => 'starts with',
            self::GreaterThan => 'greater than (after)',
            self::LessThan => 'less than (before)',
        };
    }

    /**
     * Constrain a query by this operator, for the given field and canonical
     * value. Text fields compare case-insensitively via ILIKE (wildcards and
     * escapes handled here); enum fields with plain equality; dates by UTC day
     * with exclusive bounds.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public function apply(Builder $query, Field $field, string $value): void
    {
        $column = $field->column();

        match ($this) {
            self::Equals => $field->isTextual()
                ? $query->whereLike($column, self::escapeLike($value), caseSensitive: false)
                : $query->where($column, '=', $value),
            self::NotEquals => $field->isTextual()
                ? $query->whereNotLike($column, self::escapeLike($value), caseSensitive: false)
                : $query->where($column, '!=', $value),
            self::Contains => $query->whereLike($column, '%'.self::escapeLike($value).'%', caseSensitive: false),
            self::StartsWith => $query->whereLike($column, self::escapeLike($value).'%', caseSensitive: false),
            self::GreaterThan => $query->whereDate($column, '>', $value),
            self::LessThan => $query->whereDate($column, '<', $value),
        };
    }

    /**
     * Escape the LIKE metacharacters so a user's value matches literally: the
     * wildcards `%` and `_`, and the backslash escape character itself.
     */
    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
