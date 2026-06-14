<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentStatus: string
{
    case DRAFT = 'DRAFT';
    case SCHEDULED = 'SCHEDULED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case ARCHIVED = 'ARCHIVED';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::DRAFT->value => 'Draft',
            self::SCHEDULED->value => 'Scheduled',
            self::IN_PROGRESS->value => 'In Progress',
            self::COMPLETED->value => 'Completed',
            self::ARCHIVED->value => 'Archived',
        ];
    }
}
