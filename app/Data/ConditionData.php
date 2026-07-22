<?php

namespace App\Data;

use App\Enums\Country;
use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * A leaf node asserting one fact: a {@see Field}, an {@see Operator}, and a
 * string value (dates and enum values are all carried as strings).
 *
 * Beyond the enum shape inferred for {@see $field} and {@see $operator}, the
 * per-field operator subset and value typing are validated in {@see rules()};
 * values are expected canonical (trimmed, enums cased) by the time they arrive.
 */
#[MergeValidationRules]
final class ConditionData extends FilterNodeData
{
    public function __construct(
        public readonly Field $field,
        public readonly Operator $operator,
        public readonly string $value,
    ) {
        parent::__construct('condition');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $field = is_array($payload) && is_string($payload['field'] ?? null)
            ? Field::tryFrom($payload['field'])
            : null;

        if ($field === null) {
            // Leave the inferred Field enum rule to report the unknown field.
            return [];
        }

        return [
            'operator' => [Rule::in(array_map(fn (Operator $operator) => $operator->value, $field->operators()))],
            'value' => match ($field) {
                Field::Status => [Rule::enum(Status::class)],
                Field::Country => [Rule::enum(Country::class)],
                Field::CreatedAt => ['date_format:Y-m-d'],
                Field::Name, Field::Email => [],
            },
        ];
    }
}
