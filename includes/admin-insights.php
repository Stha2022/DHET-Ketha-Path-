<?php
/**
 * Khetha Path — admin insights: where the skills gaps are.
 *
 * admin_insights($db) reads the learner tables (users, assessmentResults) and
 * returns plain arrays for admin/index.php to render. SELECT-only, explicit
 * columns — passwordHash / tempToken never leave the database.
 *
 * "Skills gap" here means two things the data can actually show:
 *   1. subject_gaps   learners aiming at careers that REQUIRE a subject they aren't
 *                     taking, or are marked below the career's minimum.
 *   2. pipeline       careers in high demand that few learners are heading towards.
 * Plus the most common Job Fit warning flags, as a "what learners struggle with" signal.
 *
 * Only each learner's LATEST result per source counts (job_fit: latest per occupation),
 * matching what includes/cv.php reads. Career reference data (titles, demand, required
 * subjects) comes from occupation-data.php — the `careers` table has no slug to join on.
 */

require_once __DIR__ . '/../occupation-data.php';

const ADMIN_GRADE_LABELS = ['grade 9' => 'Grade 9', 'grade 10' => 'Grade 10', 'grade 11' => 'Grade 11', 'grade 12' => 'Grade 12', 'out of school' => 'Post-school'];

/** @return array<string, mixed> */
function admin_insights(mysqli $db): array {
    $occupations = kp_occupations();

    // --- Learners by grade -------------------------------------------------
    $grades = [];
    $learners = 0;
    if ($res = $db->query('SELECT grade, COUNT(*) AS n FROM users GROUP BY grade')) {
        while ($r = $res->fetch_assoc()) {
            $grades[ADMIN_GRADE_LABELS[$r['grade']] ?? 'Not set'] = (int)$r['n'];
            $learners += (int)$r['n'];
        }
    }

    $grades = array_replace(array_fill_keys(array_values(ADMIN_GRADE_LABELS), 0), $grades);
    $grades = array_filter($grades);

    // --- Latest result per learner / source --------------------------------
    $chooser = [];   // userID => payload
    $choice  = [];   // userID => derived
    $jobFit  = [];   // "userID|occupation" => derived
    if ($res = $db->query('SELECT userID, source, payload, derived FROM assessmentResults ORDER BY assessmentID DESC LIMIT 50000')) {
        while ($r = $res->fetch_assoc()) {
            $uid = (int)$r['userID'];
            if ($r['source'] === 'subject_chooser' && !isset($chooser[$uid])) {
                $chooser[$uid] = json_decode((string)$r['payload'], true) ?: [];
            } elseif ($r['source'] === 'career_choice' && !isset($choice[$uid])) {
                $choice[$uid] = json_decode((string)$r['derived'], true) ?: [];
            } elseif ($r['source'] === 'job_fit') {
                $d = json_decode((string)$r['derived'], true) ?: [];
                $key = $uid . '|' . ($d['occupation_id'] ?? '');
                if (!isset($jobFit[$key])) $jobFit[$key] = $d;
            }
        }
    }

    // --- 1. Subject gaps ---------------------------------------------------
    $gaps = []; // subject => [needed, not_taking, low_mark]
    foreach ($chooser as $p) {
        $taking = array_flip(array_merge((array)($p['subjects'] ?? []), $p['maths_track'] ?? '' ? [$p['maths_track']] : []));
        $marks  = (array)($p['marks'] ?? []);
        $needs  = []; // subject => highest minimum among this learner's intended careers
        foreach ((array)($p['intended_careers'] ?? []) as $careerId) {
            foreach ($occupations[$careerId]['subject_requirements'] ?? [] as $req) {
                if ($req['necessity'] !== 'required') continue;
                $needs[$req['subject']] = max($needs[$req['subject']] ?? 0, (int)($req['min_percent'] ?? 0));
            }
        }
        foreach ($needs as $subject => $min) {
            $g = &$gaps[$subject];
            $g ??= ['needed' => 0, 'not_taking' => 0, 'low_mark' => 0];
            $g['needed']++;
            if (!isset($taking[$subject])) $g['not_taking']++;
            elseif ($min > 0 && isset($marks[$subject]) && (int)$marks[$subject] < $min) $g['low_mark']++;
            unset($g);
        }
    }
    $subjectGaps = [];
    foreach ($gaps as $subject => $g) {
        $short = $g['not_taking'] + $g['low_mark'];
        if ($short > 0) $subjectGaps[] = ['subject' => $subject, 'needed' => $g['needed'], 'not_taking' => $g['not_taking'], 'low_mark' => $g['low_mark'], 'short' => $short];
    }
    usort($subjectGaps, fn($a, $b) => $b['short'] <=> $a['short']);

    // --- 2. Demand vs learner interest -------------------------------------
    $interest = []; // occupation id => distinct learners
    foreach ($chooser + $choice as $uid => $_) {
        $ids = array_unique(array_merge((array)($chooser[$uid]['intended_careers'] ?? []), (array)($choice[$uid]['top_careers'] ?? [])));
        foreach ($ids as $id) $interest[$id] = ($interest[$id] ?? 0) + 1;
    }
    $pipeline = [];
    foreach ($occupations as $id => $occ) {
        $pipeline[] = ['title' => $occ['title'], 'field' => $occ['field'] ?? '', 'demand' => $occ['demand'] ?? 'medium', 'learners' => $interest[$id] ?? 0];
    }
    $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($pipeline, fn($a, $b) => [$rank[$a['demand']] ?? 1, $a['learners']] <=> [$rank[$b['demand']] ?? 1, $b['learners']]);

    // --- Job Fit: most common warning flags and weakest-fit careers --------
    $flags = [];
    $fitByCareer = [];
    foreach ($jobFit as $d) {
        foreach ((array)($d['flags'] ?? []) as $f) $flags[$f] = ($flags[$f] ?? 0) + 1;
        if (isset($d['overall'], $d['occupation_id'])) $fitByCareer[$d['occupation_id']][] = (int)$d['overall'];
    }
    arsort($flags);
    $allFits = array_merge(...array_values($fitByCareer ?: [[]]));

    return [
        'learners'      => $learners,
        'grades'        => $grades,
        'with_chooser'  => count($chooser),
        'with_choice'   => count($choice),
        'job_fit_count' => count($jobFit),
        'avg_fit'       => $allFits ? (int)round(array_sum($allFits) / count($allFits)) : null,
        'subject_gaps'  => $subjectGaps,
        'pipeline'      => $pipeline,
        'top_flags'     => array_slice($flags, 0, 6, true),
    ];
}
