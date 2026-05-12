<?php

namespace App\Services;

use App\Enums\FabricRollStatus;
use App\Enums\InspectionRequestStatus;
use App\Enums\InspectionStatus;
use DomainException;

/**
 * Centralized state machine for all lifecycle transitions.
 *
 * Accepts both string values ('IN_PROGRESS') and backed enum cases
 * (InspectionStatus::IN_PROGRESS) — Eloquent model casts return enums,
 * so both forms must be supported without changing every call site.
 */
class StateMachineService
{
    // -----------------------------------------------------------------------
    // Inspection transitions
    // -----------------------------------------------------------------------

    public function canInspectionTransition(string|\BackedEnum $from, string|\BackedEnum $to): bool
    {
        $fromEnum = InspectionStatus::tryFrom($this->str($from));
        if ($fromEnum === null) return false;

        return in_array(
            InspectionStatus::tryFrom($this->str($to)),
            $fromEnum->allowedTransitions(),
            strict: true
        );
    }

    public function assertInspectionTransition(string|\BackedEnum $from, string|\BackedEnum $to): void
    {
        $f = $this->str($from);
        $t = $this->str($to);
        if (! $this->canInspectionTransition($f, $t)) {
            throw new DomainException(
                "Invalid inspection transition: {$f} → {$t}. " .
                "Allowed from {$f}: " . $this->formatAllowed(
                    InspectionStatus::tryFrom($f)?->allowedTransitions() ?? []
                )
            );
        }
    }

    // -----------------------------------------------------------------------
    // FabricRoll transitions
    // -----------------------------------------------------------------------

    public function canRollTransition(string|\BackedEnum $from, string|\BackedEnum $to): bool
    {
        $fromEnum = FabricRollStatus::tryFrom($this->str($from));
        if ($fromEnum === null) return false;

        return in_array(
            FabricRollStatus::tryFrom($this->str($to)),
            $fromEnum->allowedTransitions(),
            strict: true
        );
    }

    public function assertRollTransition(string|\BackedEnum $from, string|\BackedEnum $to): void
    {
        $f = $this->str($from);
        $t = $this->str($to);
        if (! $this->canRollTransition($f, $t)) {
            throw new DomainException(
                "Invalid fabric roll transition: {$f} → {$t}. " .
                "Allowed from {$f}: " . $this->formatAllowed(
                    FabricRollStatus::tryFrom($f)?->allowedTransitions() ?? []
                )
            );
        }
    }

    // -----------------------------------------------------------------------
    // InspectionRequest transitions
    // -----------------------------------------------------------------------

    public function canRequestTransition(string|\BackedEnum $from, string|\BackedEnum $to): bool
    {
        $fromEnum = InspectionRequestStatus::tryFrom($this->str($from));
        if ($fromEnum === null) return false;

        return in_array(
            InspectionRequestStatus::tryFrom($this->str($to)),
            $fromEnum->allowedTransitions(),
            strict: true
        );
    }

    public function assertRequestTransition(string|\BackedEnum $from, string|\BackedEnum $to): void
    {
        $f = $this->str($from);
        $t = $this->str($to);
        if (! $this->canRequestTransition($f, $t)) {
            throw new DomainException(
                "Invalid inspection request transition: {$f} → {$t}. " .
                "Allowed from {$f}: " . $this->formatAllowed(
                    InspectionRequestStatus::tryFrom($f)?->allowedTransitions() ?? []
                )
            );
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Normalize a string or BackedEnum to its string value. */
    private function str(string|\BackedEnum $v): string
    {
        return $v instanceof \BackedEnum ? $v->value : $v;
    }

    private function formatAllowed(array $cases): string
    {
        if (empty($cases)) return 'none (final state)';
        return implode(', ', array_map(fn ($c) => $c->value, $cases));
    }
}
