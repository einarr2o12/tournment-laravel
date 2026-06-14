<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchStage: string
{
    case GROUP = 'GROUP';
    case ROUND_OF_64 = 'ROUND_OF_64';
    case ROUND_OF_32 = 'ROUND_OF_32';
    case ROUND_OF_16 = 'ROUND_OF_16';
    case QUARTERFINAL = 'QUARTERFINAL';
    case SEMIFINAL = 'SEMIFINAL';
    case FINAL = 'FINAL';
    case THIRD_PLACE = 'THIRD_PLACE';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::GROUP->value => 'Group Stage',
            self::ROUND_OF_64->value => 'Round of 64',
            self::ROUND_OF_32->value => 'Round of 32',
            self::ROUND_OF_16->value => 'Round of 16',
            self::QUARTERFINAL->value => 'Quarterfinal',
            self::SEMIFINAL->value => 'Semifinal',
            self::FINAL->value => 'Final',
            self::THIRD_PLACE->value => 'Third Place Playoff',
        ];
    }
}
