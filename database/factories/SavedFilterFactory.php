<?php

namespace Database\Factories;

use App\Data\FilterDefinition;
use App\Enums\BooleanOperator;
use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SavedFilter>
 */
class SavedFilterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => Str::title(rtrim(fake()->unique()->sentence(3), '.')),
            'definition' => self::conditionEnvelope(Field::Status, Operator::Equals, Status::Active->value),
            'is_shared' => false,
        ];
    }

    /**
     * Assign ownership to a specific user.
     */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }

    /**
     * Mark the filter as shared with everyone.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => ['is_shared' => true]);
    }

    /**
     * Store a specific `{version, root}` envelope.
     *
     * @param  array<string, mixed>  $envelope
     */
    public function withDefinition(array $envelope): static
    {
        return $this->state(fn (array $attributes) => ['definition' => $envelope]);
    }

    /**
     * A single-condition filter — the per-operator preset.
     */
    public function condition(Field $field, Operator $operator, string $value): static
    {
        return $this->withDefinition(self::conditionEnvelope($field, $operator, $value));
    }

    /**
     * A nested definition: (status pending OR suspended) AND (country DE OR FR)
     * AND created_at > date.
     */
    public function nested(): static
    {
        return $this->withDefinition(self::envelope([
            'type' => 'group',
            'boolean' => BooleanOperator::And->value,
            'children' => [
                self::group(BooleanOperator::Or, [
                    self::conditionNode(Field::Status, Operator::Equals, Status::Pending->value),
                    self::conditionNode(Field::Status, Operator::Equals, Status::Suspended->value),
                ]),
                self::group(BooleanOperator::Or, [
                    self::conditionNode(Field::Country, Operator::Equals, 'DE'),
                    self::conditionNode(Field::Country, Operator::Equals, 'FR'),
                ]),
                self::conditionNode(Field::CreatedAt, Operator::GreaterThan, '2025-01-01'),
            ],
        ]));
    }

    /**
     * Wrap a single condition in a canonical AND-root envelope.
     *
     * @return array{version: int, root: array<string, mixed>}
     */
    private static function conditionEnvelope(Field $field, Operator $operator, string $value): array
    {
        return self::envelope(self::group(BooleanOperator::And, [
            self::conditionNode($field, $operator, $value),
        ]));
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array{version: int, root: array<string, mixed>}
     */
    private static function envelope(array $root): array
    {
        return ['version' => FilterDefinition::VERSION, 'root' => $root];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private static function group(BooleanOperator $boolean, array $children): array
    {
        return ['type' => 'group', 'boolean' => $boolean->value, 'children' => $children];
    }

    /**
     * @return array<string, mixed>
     */
    private static function conditionNode(Field $field, Operator $operator, string $value): array
    {
        return ['type' => 'condition', 'field' => $field->value, 'operator' => $operator->value, 'value' => $value];
    }
}
