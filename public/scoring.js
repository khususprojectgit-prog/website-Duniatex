/** Duniatex inspection score & grade (shared with operator/QC UIs). */
const SCORE_MULTIPLIER = 1646;

function calcInspectionScore(totalPoints, lengthMeter, lebar) {
  const len = parseFloat(lengthMeter);
  const w = parseFloat(lebar);
  const denom = len * w;
  if (!denom || denom <= 0) return 0;
  return (totalPoints * SCORE_MULTIPLIER) / denom;
}

function calcInspectionGrade(score) {
  const s = parseFloat(score);
  if (isNaN(s)) return '';
  if (s <= 15) return 'A';
  if (s >= 20) return 'BS';
  return 'B';
}

function gradeBarColor(grade) {
  const g = (grade || '').toUpperCase();
  if (g === 'A') return 'var(--success)';
  if (g === 'B') return 'var(--warning)';
  if (g === 'BS') return 'var(--danger)';
  return 'var(--muted)';
}

function gradeBarPercent(score) {
  return Math.min(100, (parseFloat(score) / 30) * 100);
}
