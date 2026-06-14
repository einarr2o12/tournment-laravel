<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryType: string
{
    case MENS_SINGLES = 'MENS_SINGLES';
    case WOMENS_SINGLES = 'WOMENS_SINGLES';
    case MENS_DOUBLES = 'MENS_DOUBLES';
    case WOMENS_DOUBLES = 'WOMENS_DOUBLES';
    case MIXED_DOUBLES = 'MIXED_DOUBLES';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::MENS_SINGLES->value => "Men's Singles",
            self::WOMENS_SINGLES->value => "Women's Singles",
            self::MENS_DOUBLES->value => "Men's Doubles",
            self::WOMENS_DOUBLES->value => "Women's Doubles",
            self::MIXED_DOUBLES->value => 'Mixed Doubles',
        ];
    }
}
