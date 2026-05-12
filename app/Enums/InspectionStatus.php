<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case SUBMITTED   = 'SUBMITTED';
    case VALIDATED   = 'VALIDATED';
    case REJECTED    = 'REJECTED';

    /** States from which the inspection can be edited (defects added). */
    public function isEditable(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /** States where no further QC action is possible. */
    public function isFinal(): bool
    {
        return match ($this) {
            self::VALIDATED, self::REJECTED => true,
            default                         => false,
        };
    }

    /** States where QC can validate or reject. */
    public function isActionable(): bool
    {
        return $this === self::SUBMITTED;
    }

    /** Valid next states from this state. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::IN_PROGRESS => [self::SUBMITTED],
            self::SUBMITTED   => [self::VALIDATED, self::REJECTED],
            default           => [],
        };
    }
}
