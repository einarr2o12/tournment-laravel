<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case REFEREE = 'REFEREE';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ADMIN->value => 'Administrator',
            self::REFEREE->value => 'Referee',
        ];
    }
}
