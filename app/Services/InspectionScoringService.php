<?php

namespace App\Services;

class InspectionScoringService
{
    public const MULTIPLIER = 1646;

    public const GRADE_A_MAX = 15;

    public const GRADE_BS_MIN = 20;

    /**
     * Score = total_points × 1646 ÷ (panjang × lebar)
     *
     * @return array{score: float, grade: string}
     */
    public function calculate(int $totalPoints, float $lengthMeter, float $lebar): array
    {
        if ($lengthMeter <= 0) {
            throw new \InvalidArgumentException('Panjang kain harus lebih dari 0.');
        }
        if ($lebar <= 0) {
            throw new \InvalidArgumentException('Lebar kain harus lebih dari 0.');
        }

        $score = round(($totalPoints * self::MULTIPLIER) / ($lengthMeter * $lebar), 2);
        $grade = $this->gradeFromScore($score);

        return ['score' => $score, 'grade' => $grade];
    }

    /** ≤15 → A, >15 & <20 → B, ≥20 → BS */
    public function gradeFromScore(float $score): string
    {
        if ($score <= self::GRADE_A_MAX) {
            return 'A';
        }

        if ($score >= self::GRADE_BS_MIN) {
            return 'BS';
        }

        return 'B';
    }
}
