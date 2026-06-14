<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentFormat: string
{
    case SINGLE_ELIMINATION = 'SINGLE_ELIMINATION';
    case ROUND_ROBIN = 'ROUND_ROBIN';
    case GROUP_KNOCKOUT = 'GROUP_KNOCKOUT';
    case SWISS = 'SWISS';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::SINGLE_ELIMINATION->value => 'Single Elimination',
            self::ROUND_ROBIN->value => 'Round Robin',
            self::GROUP_KNOCKOUT->value => 'Group + Knockout',
            self::SWISS->value => 'Swiss',
        ];
    }
}
