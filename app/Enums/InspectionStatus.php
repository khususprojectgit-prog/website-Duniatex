<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case IN_PROGRESS  = 'IN_PROGRESS';
    case SUBMITTED    = 'SUBMITTED';
    case VALIDATED    = 'VALIDATED';
    case REJECTED     = 'REJECTED';
    case QC_VALIDATED = 'QC_VALIDATED';
    case RELEASED     = 'RELEASED';

    /** States from which the inspection can be edited (defects added). */
    public function isEditable(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /** States where no further QC/Admin action is possible. */
    public function isFinal(): bool
    {
        return match ($this) {
            self::RELEASED, self::REJECTED, self::VALIDATED => true,
            default                                         => false,
        };
    }

    /** States where QC/Admin can validate or reject. */
    public function isActionable(): bool
    {
        return $this === self::QC_VALIDATED;
    }

    /** Valid next states from this state. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::IN_PROGRESS  => [self::QC_VALIDATED],
            self::QC_VALIDATED => [self::RELEASED, self::REJECTED],
            self::RELEASED     => [],
            self::REJECTED     => [],
            self::SUBMITTED    => [self::VALIDATED, self::REJECTED],
            self::VALIDATED    => [self::RELEASED],
        };
    }
}
