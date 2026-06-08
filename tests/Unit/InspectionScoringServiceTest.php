<?php

namespace Tests\Unit;

use App\Services\InspectionScoringService;
use PHPUnit\Framework\TestCase;

class InspectionScoringServiceTest extends TestCase
{
    private InspectionScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new InspectionScoringService;
    }

    public function test_score_formula(): void
    {
        // 10 points × 1646 ÷ (100 × 180) = 16460 / 18000 ≈ 0.91
        $result = $this->scoring->calculate(10, 100, 180);
        $this->assertSame(0.91, $result['score']);
        $this->assertSame('A', $result['grade']);
    }

    public function test_grade_boundaries(): void
    {
        $this->assertSame('A', $this->scoring->gradeFromScore(15));
        $this->assertSame('B', $this->scoring->gradeFromScore(15.01));
        $this->assertSame('B', $this->scoring->gradeFromScore(19.99));
        $this->assertSame('BS', $this->scoring->gradeFromScore(20));
    }

    public function test_high_defect_score_is_bs(): void
    {
        $result = $this->scoring->calculate(50, 50, 50);
        $this->assertGreaterThanOrEqual(20, $result['score']);
        $this->assertSame('BS', $result['grade']);
    }
}
