<?php

namespace App\Enums;

enum Status: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    /**
     * The human label shown in the builder's status select and table badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending',
            self::Suspended => 'Suspended',
            self::Deactivated => 'Deactivated',
        };
    }
}
