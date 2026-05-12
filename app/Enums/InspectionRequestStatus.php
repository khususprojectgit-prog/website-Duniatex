<?php

namespace App\Enums;

enum InspectionRequestStatus: string
{
    case NEW         = 'NEW';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED   = 'COMPLETED';

    /** Valid next states from this state. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NEW         => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::COMPLETED],
            self::COMPLETED   => [],
        };
    }

    public function isFinal(): bool
    {
        return $this === self::COMPLETED;
    }
}
