<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case WALKOVER = 'WALKOVER';
    case CANCELLED = 'CANCELLED';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::SCHEDULED->value => 'Scheduled',
            self::IN_PROGRESS->value => 'In Progress',
            self::COMPLETED->value => 'Completed',
            self::WALKOVER->value => 'Walkover',
            self::CANCELLED->value => 'Cancelled',
        ];
    }
}
