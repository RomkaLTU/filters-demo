<?php

namespace App\Enums;

/**
 * The curated ISO-3166 alpha-2 country list the app recognises. Membership is
 * app-level only: the database checks shape (two uppercase letters), not this
 * list, so entries can change without a migration.
 */
enum Country: string
{
    case US = 'US';
    case CA = 'CA';
    case GB = 'GB';
    case IE = 'IE';
    case DE = 'DE';
    case FR = 'FR';
    case ES = 'ES';
    case PT = 'PT';
    case IT = 'IT';
    case NL = 'NL';
    case SE = 'SE';
    case BG = 'BG';
    case PL = 'PL';
    case BR = 'BR';
    case MX = 'MX';
    case AR = 'AR';
    case IN = 'IN';
    case CN = 'CN';
    case JP = 'JP';
    case KR = 'KR';
    case SG = 'SG';
    case AU = 'AU';
    case NZ = 'NZ';
    case ZA = 'ZA';
    case NG = 'NG';
    case EG = 'EG';

    /**
     * The country's display name, shown in the builder's country select and
     * the users table.
     */
    public function label(): string
    {
        return match ($this) {
            self::US => 'United States',
            self::CA => 'Canada',
            self::GB => 'United Kingdom',
            self::IE => 'Ireland',
            self::DE => 'Germany',
            self::FR => 'France',
            self::ES => 'Spain',
            self::PT => 'Portugal',
            self::IT => 'Italy',
            self::NL => 'Netherlands',
            self::SE => 'Sweden',
            self::BG => 'Bulgaria',
            self::PL => 'Poland',
            self::BR => 'Brazil',
            self::MX => 'Mexico',
            self::AR => 'Argentina',
            self::IN => 'India',
            self::CN => 'China',
            self::JP => 'Japan',
            self::KR => 'South Korea',
            self::SG => 'Singapore',
            self::AU => 'Australia',
            self::NZ => 'New Zealand',
            self::ZA => 'South Africa',
            self::NG => 'Nigeria',
            self::EG => 'Egypt',
        };
    }
}
