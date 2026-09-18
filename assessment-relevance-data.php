<?php
/**
 * Khetha Path — grade-aware relevance for the three core assessments
 * (Career Choice, Subject Chooser, Job Fit) and Subject Chooser's mode.
 * Pure config/data, no output. Dashboard, Subject Chooser and My Path
 * read from here instead of branching on grade inline.
 *
 * All three assessments stay visible at every grade — this never
 * hard-locks one. It only changes ordering, tile prominence, Subject
 * Chooser's mode, and results copy (handled elsewhere, driven by these
 * functions).
 *
 * Grade keys match $_SESSION['user']['grade'] exactly as collected by
 * register.php's <select name="grade"> options: 'Grade 9', 'Grade 10',
 * 'Grade 11', 'Grade 12', 'Post-school' (the out-of-school / work-seeker
 * case). There is no 'Grade 8' option in this app today, so the relevance
 * matrix and SELECT-mode routing below only cover Grade 9 upward.
 */

const AR_PRIORITY_PRIMARY   = 'primary';
const AR_PRIORITY_SECONDARY = 'secondary';
const AR_PRIORITY_TERTIARY  = 'tertiary';

const AR_ASSESSMENT_CAREER_CHOICE   = 'career_choice';
const AR_ASSESSMENT_SUBJECT_CHOOSER = 'subject_chooser';
const AR_ASSESSMENT_JOB_FIT         = 'job_fit';

const AR_MODE_SELECT     = 'select';
const AR_MODE_REVIEW     = 'review';
const AR_MODE_DIAGNOSTIC = 'diagnostic';

/**
 * Priority per assessment per grade. Column order within each grade row
 * is also the tie-break order when two assessments share a priority.
 */
function ar_relevance_matrix(): array
{
    return [
        'Grade 9' => [
            AR_ASSESSMENT_CAREER_CHOICE   => AR_PRIORITY_PRIMARY,
            AR_ASSESSMENT_SUBJECT_CHOOSER => AR_PRIORITY_PRIMARY,
            AR_ASSESSMENT_JOB_FIT         => AR_PRIORITY_TERTIARY,
        ],
        'Grade 10' => [
            AR_ASSESSMENT_CAREER_CHOICE   => AR_PRIORITY_PRIMARY,
            AR_ASSESSMENT_SUBJECT_CHOOSER => AR_PRIORITY_PRIMARY,
            AR_ASSESSMENT_JOB_FIT         => AR_PRIORITY_SECONDARY,
        ],
        'Grade 11' => [
            AR_ASSESSMENT_CAREER_CHOICE   => AR_PRIORITY_SECONDARY,
            AR_ASSESSMENT_SUBJECT_CHOOSER => AR_PRIORITY_SECONDARY,
            AR_ASSESSMENT_JOB_FIT         => AR_PRIORITY_PRIMARY,
        ],
        'Grade 12' => [
            AR_ASSESSMENT_CAREER_CHOICE   => AR_PRIORITY_TERTIARY,
            AR_ASSESSMENT_SUBJECT_CHOOSER => AR_PRIORITY_TERTIARY,
            AR_ASSESSMENT_JOB_FIT         => AR_PRIORITY_PRIMARY,
        ],
        // Out-of-school / work seeker.
        'Post-school' => [
            AR_ASSESSMENT_CAREER_CHOICE   => AR_PRIORITY_PRIMARY,
            AR_ASSESSMENT_SUBJECT_CHOOSER => AR_PRIORITY_TERTIARY,
            AR_ASSESSMENT_JOB_FIT         => AR_PRIORITY_PRIMARY,
        ],
    ];
}

/** Grade to fall back to for a grade value not in the matrix (defensive; register.php only emits known values). */
const AR_FALLBACK_GRADE = 'Grade 11';

function ar_priority_for(string $grade, string $assessment): string
{
    $matrix = ar_relevance_matrix();
    $row = $matrix[$grade] ?? $matrix[AR_FALLBACK_GRADE];
    return $row[$assessment] ?? AR_PRIORITY_SECONDARY;
}

function ar_priority_rank(string $priority): int
{
    $order = [AR_PRIORITY_PRIMARY => 0, AR_PRIORITY_SECONDARY => 1, AR_PRIORITY_TERTIARY => 2];
    return $order[$priority] ?? 3;
}

/**
 * Assessment keys in the order they should render for a grade, primary
 * first. Ties keep the matrix row's declared column order.
 */
function ar_ordered_assessments(string $grade): array
{
    $matrix = ar_relevance_matrix();
    $row = $matrix[$grade] ?? $matrix[AR_FALLBACK_GRADE];
    $keys = array_keys($row);
    usort($keys, fn($a, $b) => ar_priority_rank($row[$a]) <=> ar_priority_rank($row[$b]));
    return $keys;
}

/**
 * Short hint shown on a tertiary tile instead of its normal description
 * (e.g. Job Fit at Grade 9: "Most useful from Grade 11"). Null means no
 * hint copy exists yet for that grade/assessment pair — caller should
 * fall back to the assessment's normal description.
 */
function ar_tertiary_hint(string $grade, string $assessment): ?string
{
    $hints = [
        'Grade 9' => [
            AR_ASSESSMENT_JOB_FIT => 'Most useful from Grade 11',
        ],
        'Grade 12' => [
            AR_ASSESSMENT_CAREER_CHOICE   => 'Useful for confirming direction, not for choosing subjects now',
            AR_ASSESSMENT_SUBJECT_CHOOSER => 'Your subjects are locked in — see what they open',
        ],
        'Post-school' => [
            AR_ASSESSMENT_SUBJECT_CHOOSER => 'For learners still choosing school subjects',
        ],
    ];
    return $hints[$grade][$assessment] ?? null;
}

/**
 * Subject Chooser's mode, derived from grade. Grade 9 has no
 * chosen-subjects history yet (SELECT); Grade 10 has chosen subjects
 * that can still realistically change (REVIEW); Grade 11/12 and
 * Post-school are read-only against a fixed subject history
 * (DIAGNOSTIC) — Post-school gets the same treatment as Gr11/12 since
 * there's no "this year's subjects" to review or select.
 */
function ar_subject_chooser_mode(string $grade): string
{
    return match ($grade) {
        'Grade 9' => AR_MODE_SELECT,
        'Grade 10' => AR_MODE_REVIEW,
        default => AR_MODE_DIAGNOSTIC,
    };
}

/**
 * True when a Grade 9/10 user opening Subject Chooser without a Career
 * Choice result should be offered the (skippable) interstitial to do
 * Career Choice first — Subject Chooser's SELECT/REVIEW output works
 * backwards from a career target at these grades.
 */
function ar_subject_chooser_needs_career_first(string $grade, bool $hasCareerChoiceResult): bool
{
    return in_array($grade, ['Grade 9', 'Grade 10'], true) && !$hasCareerChoiceResult;
}
