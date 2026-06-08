<?php

namespace App\Enums;

enum FabricRollStatus: string
{
    case NEW          = 'NEW';
    case IN_PROGRESS  = 'IN_PROGRESS';
    case SUBMITTED    = 'SUBMITTED';
    case VALIDATED    = 'VALIDATED';
    case PENDING      = 'PENDING';   // Returned by QC for re-inspection
    case QC_VALIDATED = 'QC_VALIDATED';
    case RELEASED     = 'RELEASED';

    /** Roll is available for an operator to start inspection. */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::NEW, self::PENDING => true,
            default                  => false,
        };
    }

    /** Roll has reached its final, immutable state. */
    public function isFinal(): bool
    {
        return $this === self::RELEASED;
    }

    /** Valid next states from this state. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NEW          => [self::IN_PROGRESS],
            self::IN_PROGRESS  => [self::QC_VALIDATED],
            self::QC_VALIDATED => [self::RELEASED, self::PENDING],
            self::PENDING      => [self::IN_PROGRESS],
            self::RELEASED     => [],
            self::SUBMITTED    => [self::VALIDATED, self::PENDING],
            self::VALIDATED    => [self::RELEASED],
        };
    }
}
