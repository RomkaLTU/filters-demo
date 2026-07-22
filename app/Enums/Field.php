<?php

namespace App\Enums;

enum Field: string
{
    case Name = 'name';
    case Email = 'email';
    case Status = 'status';
    case Country = 'country';
    case CreatedAt = 'created_at';

    /**
     * The operators this field accepts. Enforced in validation and mirrored
     * by the UI's operator select.
     *
     * @return list<Operator>
     */
    public function operators(): array
    {
        return match ($this) {
            self::Name, self::Email => [
                Operator::Equals,
                Operator::NotEquals,
                Operator::Contains,
                Operator::StartsWith,
            ],
            self::Status, self::Country => [
                Operator::Equals,
                Operator::NotEquals,
            ],
            self::CreatedAt => [
                Operator::GreaterThan,
                Operator::LessThan,
            ],
        };
    }

    public function allows(Operator $operator): bool
    {
        return in_array($operator, $this->operators(), strict: true);
    }

    /**
     * The human label shown in the builder's field select and column headers.
     */
    public function label(): string
    {
        return match ($this) {
            self::Name => 'Name',
            self::Email => 'Email',
            self::Status => 'Status',
            self::Country => 'Country',
            self::CreatedAt => 'Created at',
        };
    }

    /**
     * The users-table column this field tests. The one place a field name
     * becomes a SQL identifier, so no raw column strings live in the pipeline.
     */
    public function column(): string
    {
        return $this->value;
    }

    /**
     * Whether the field holds free text (compared case-insensitively via ILIKE)
     * rather than a canonical enum value (compared with plain equality).
     */
    public function isTextual(): bool
    {
        return $this === self::Name || $this === self::Email;
    }

    /**
     * Canonicalise a raw value for this field: trim always, then lowercase
     * status and uppercase country so stored enum values are canonical. The
     * result is what gets validated for membership and persisted.
     */
    public function canonicalize(string $value): string
    {
        return trim($value) |> match ($this) {
            self::Status => strtolower(...),
            self::Country => strtoupper(...),
            default => static fn (string $trimmed): string => $trimmed,
        };
    }
}
