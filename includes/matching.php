<?php
/**
 * Khetha Path — ranks occupations against a learner profile.
 * Pure scoring, no output, and no storage access: pass it profile_get().
 *
 * Two RIASEC signals, each scored as cosine similarity against the
 * occupation's own riasec{} vector (the same measure Career Choice's
 * cq_match_occupations() uses):
 *   - the Career Choice result (career_quiz.scores), once the quiz is done
 *   - the interest chips (riasec_from_interests), available from registration
 * Both present: 70% quiz + 30% interests. Only one: that one alone.
 * Neither: every occupation scores 0 and has no reasons.
 */

require_once __DIR__ . '/../occupation-data.php';
require_once __DIR__ . '/../data/interests.php';

const MATCH_QUIZ_WEIGHT = 0.70;
const MATCH_INTEREST_WEIGHT = 0.30;
const MATCH_MAX_INTEREST_REASONS = 3;

/**
 * @param array $profile As returned by profile_get().
 * @return array[] Best first. Each: id, title, field, riasec_code, score (0-100), reasons (string[]).
 */
function rank_careers(array $profile): array {
    $dims = ['R', 'I', 'A', 'S', 'E', 'C'];

    $quiz = $profile['career_quiz'] ?? [];
    $quizCode = (string)($quiz['code'] ?? '');
    $quizVector = _match_vector($quiz['scores'] ?? [], $dims);
    $hasQuiz = $quizCode !== '' && array_sum($quizVector) > 0;

    $interestVector = _match_vector($profile['riasec_from_interests'] ?? [], $dims);
    $hasInterests = array_sum($interestVector) > 0;

    $chips = kp_interest_chips();
    $learnerChips = [];
    foreach ((array)($profile['interests'] ?? []) as $value) {
        $label = is_string($value) ? kp_interest_label($value) : null;
        if ($label !== null) $learnerChips[$label] = $chips[$label];
    }

    $ranked = [];
    foreach (kp_occupations() as $occ) {
        $occVector = _match_vector($occ['riasec'] ?? [], $dims);
        $code = $occ['riasec_code'];

        $quizFit = $hasQuiz ? _match_cosine($quizVector, $occVector) : 0.0;
        $interestFit = $hasInterests ? _match_cosine($interestVector, $occVector) : 0.0;
        $fit = match (true) {
            $hasQuiz && $hasInterests => MATCH_QUIZ_WEIGHT * $quizFit + MATCH_INTEREST_WEIGHT * $interestFit,
            $hasQuiz => $quizFit,
            $hasInterests => $interestFit,
            default => 0.0,
        };

        $reasons = [];
        if ($hasInterests) {
            // A chip is a reason when one of its codes is among the occupation's
            // two leading letters (a third-letter match is too weak to call an
            // interest match); the earlier that letter, the stronger the reason.
            $matched = [];
            foreach ($learnerChips as $label => $codes) {
                $best = null;
                foreach ($codes as $c) {
                    $pos = strpos(substr($code, 0, 2), $c);
                    if ($pos !== false && ($best === null || $pos < $best)) $best = $pos;
                }
                if ($best !== null) $matched[$label] = $best;
            }
            asort($matched); // stable: ties keep the order the learner picked them
            $names = array_slice(array_keys($matched), 0, MATCH_MAX_INTEREST_REASONS);
            if ($names) $reasons[] = 'Matches your interests: ' . implode(', ', $names);
        }
        if ($hasQuiz && count(array_intersect(str_split($quizCode), str_split($code))) >= 2) {
            $reasons[] = 'Fits your Career Choice result (' . $quizCode . ')';
        }

        $ranked[] = [
            'id' => $occ['id'], 'title' => $occ['title'], 'field' => $occ['field'],
            'riasec_code' => $code, 'score' => (int)round($fit * 100), 'reasons' => $reasons,
        ];
    }

    usort($ranked, fn($a, $b) => [$b['score'], $a['title']] <=> [$a['score'], $b['title']]);
    return $ranked;
}

/** @return float[] Values for $dims in order, missing dimensions as 0. */
function _match_vector(array $byDim, array $dims): array {
    return array_map(fn($d) => (float)($byDim[$d] ?? 0), $dims);
}

function _match_cosine(array $a, array $b): float {
    $dot = $magA = $magB = 0.0;
    foreach ($a as $i => $x) {
        $dot += $x * $b[$i];
        $magA += $x * $x;
        $magB += $b[$i] * $b[$i];
    }
    return ($magA > 0 && $magB > 0) ? $dot / (sqrt($magA) * sqrt($magB)) : 0.0;
}
