<?php

namespace App\Services;

use App\Models\Application;

/**
 * ZamAdmit Requirements-Match Ranking Algorithm
 * ─────────────────────────────────────────────
 * Produces a deterministic 0–100 score expressing how well an applicant
 * satisfies a programme's published entry requirements.
 *
 * ECZ grading is INVERTED: 1 = distinction (best), 9 = fail (worst).
 * Therefore  margin = min_grade − student_grade,  and a POSITIVE margin
 * means the student EXCEEDS the requirement.
 *
 * Per-subject scoring:
 *   margin ≥ 1   →  70 + 10 × margin   (capped at 100)   exceeds
 *   margin = 0   →  70                                    meets exactly
 *   margin = −1  →  25                                    near miss
 *   margin ≤ −2  →   0                                    clear miss
 *   no result    →   0                                    subject absent
 *
 * Overall score = round(average of per-subject points).
 * Programmes with no requirements return NULL ("unranked", not 0%).
 *
 * Rationale for the constants:
 *   • "Meets" = 70, not 100 — reserves the 70–100 band to separate
 *     degrees of excellence; otherwise all eligible applicants tie.
 *   • +10 per margin point — rewards excellence steeply; the per-subject
 *     cap stops one stellar result outweighing failures elsewhere.
 *   • Near miss = 25 — keeps borderline candidates visible and ordered
 *     (real institutions exercise discretion at one grade short) while
 *     remaining below half of "meets", so accumulated near-misses can
 *     never outrank a fully qualified candidate.
 *   • Averaging (not summing) normalizes across programmes with
 *     different requirement counts.
 *
 * This is intentionally NOT an AI component: admission rankings must be
 * reproducible and explainable to an appeals board. The LLM recommender
 * (student-facing) and this ranker (admin-facing) are separate tools.
 */
class MatchScoreService
{
    private const MEETS_POINTS     = 70;
    private const MARGIN_BONUS     = 10;
    private const SUBJECT_CAP      = 100;
    private const NEAR_MISS_POINTS = 25;

    public function score(Application $application): ?int
    {
        $requirements = $application->programme?->requirements;
        $grades       = $application->user?->grades;

        if (!$requirements || $requirements->isEmpty()) {
            return null; // nothing to measure against — unranked
        }

        // Map: normalized subject name → best (lowest) grade held.
        // Handles re-sits: the student's strongest result counts.
        $gradeMap = [];
        foreach ($grades ?? [] as $g) {
            $key = mb_strtolower(trim($g->subject));
            if (!isset($gradeMap[$key]) || $g->grade < $gradeMap[$key]) {
                $gradeMap[$key] = (int) $g->grade;
            }
        }

        $total = 0;

        foreach ($requirements as $req) {
            $key = mb_strtolower(trim($req->subject));

            if (!array_key_exists($key, $gradeMap)) {
                continue; // subject absent → 0 points
            }

            $margin = (int) $req->min_grade - $gradeMap[$key];

            if ($margin >= 0) {
                $total += min(
                    self::SUBJECT_CAP,
                    self::MEETS_POINTS + $margin * self::MARGIN_BONUS,
                );
            } elseif ($margin === -1) {
                $total += self::NEAR_MISS_POINTS;
            }
            // margin ≤ −2 → 0 points
        }

        return (int) round($total / $requirements->count());
    }
}