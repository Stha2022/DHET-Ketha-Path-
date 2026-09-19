<?php
/**
 * Khetha Path — admin insights, read from the khetha_path database.
 *
 * admin_insights($db, $grade) returns plain arrays for index.php (same folder) to render.
 * SELECT-only, explicit columns — passwordHash / tempToken never leave the database.
 *
 *   funnel          how far learners get: registered → Subject Chooser → Career Choice → Job Fit
 *   warnings        early-warning groups: learners who need someone to step in, and why
 *   interests       what learners are drawn to: personality type, interest chips, career fields
 *   subject_gaps    required subjects learners are short of for the careers they want
 *   pipeline        in-demand careers few learners are heading towards
 *   top_flags       the most common Job Fit warnings
 *
 * Only each learner's LATEST result per source counts (job_fit: latest per occupation),
 * matching what includes/cv.php reads. Career reference data (titles, demand, fields, required
 * subjects) comes from occupation-data.php — the `careers` table has no slug to join on.
 */

require_once __DIR__ . '/../occupation-data.php';
require_once __DIR__ . '/../data/interests.php';
require_once __DIR__ . '/../database/db-connection.php';

const ADMIN_GRADE_LABELS = ['grade 9' => 'Grade 9', 'grade 10' => 'Grade 10', 'grade 11' => 'Grade 11', 'grade 12' => 'Grade 12', 'out of school' => 'Post-school'];
const ADMIN_LOCKED_GRADES = ['grade 11', 'grade 12', 'out of school']; // subjects can no longer be changed
const ADMIN_INACTIVE_DAYS = 14;

/**
 * Early-warning groups, most urgent first. level: high = act now, medium = follow up soon,
 * low = nudge. 'action' is what an advisor would do about it.
 */
function admin_warning_groups(): array {
    return [
        'locked_gap'   => ['level' => 'high',   'label' => 'Missing a required subject — too late to switch',
                           'action' => 'Grade 11+: show an alternative route (TVET, bridging, extended degree) or a related career.'],
        'maths_lit'    => ['level' => 'high',   'label' => 'Maths Literacy, aiming for a Mathematics career',
                           'action' => 'The most common blocked route. Talk through the Maths requirement or related careers early.'],
        'switch_now'   => ['level' => 'medium', 'label' => 'Missing a required subject — can still switch',
                           'action' => 'Grade 9–10: subject choices are still open. Flag this before choices lock.'],
        'low_marks'    => ['level' => 'medium', 'label' => 'Below the minimum mark for a required subject',
                           'action' => 'Point to tutoring or support in that subject, and check the entry requirements.'],
        'poor_fit'     => ['level' => 'medium', 'label' => 'Poor Job Fit for every career checked',
                           'action' => 'Their chosen careers clash with what they value. Suggest Career Choice or an advisor chat.'],
        'no_direction' => ['level' => 'medium', 'label' => 'Grade 11+ with no career in mind',
                           'action' => 'Close to leaving school without a direction. Invite them to Career Choice.'],
        'inactive'     => ['level' => 'low',    'label' => 'Registered but hasn\'t used any tool',
                           'action' => 'Signed up ' . ADMIN_INACTIVE_DAYS . '+ days ago and never started. Send a reminder.'],
    ];
}

/** @return array<string, mixed> */
function admin_insights(mysqli $db, string $grade = ''): array {
    $occupations = kp_occupations();
    $grade = isset(ADMIN_GRADE_LABELS[$grade]) ? $grade : '';

    // --- Learners ----------------------------------------------------------
    // users.interests only exists once database/migration_cv_edits.sql has been applied.
    $hasInterests = (bool)(($r = $db->query("SHOW COLUMNS FROM users LIKE 'interests'")) && $r->num_rows);
    $sql = 'SELECT userID, firstName, lastName, email, grade, createdAt' . ($hasInterests ? ', interests' : '') . ' FROM users'
         . ($grade !== '' ? ' WHERE grade = ?' : '');
    $stmt = $db->prepare($sql);
    if ($grade !== '') $stmt->bind_param('s', $grade);
    $learners = [];
    if ($stmt && $stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $learners[(int)$r['userID']] = $r;
    }
    $allGrades = []; // always across every grade, so the filter shows what it narrows
    if ($res = $db->query('SELECT grade, COUNT(*) AS n FROM users GROUP BY grade')) {
        while ($r = $res->fetch_assoc()) $allGrades[$r['grade']] = (int)$r['n'];
    }

    // --- Latest result per learner / source --------------------------------
    $chooser = []; $choice = []; $jobFit = [];
    if ($learners && ($res = $db->query('SELECT userID, source, payload, derived FROM assessmentResults ORDER BY assessmentID DESC LIMIT 50000'))) {
        while ($r = $res->fetch_assoc()) {
            $uid = (int)$r['userID'];
            if (!isset($learners[$uid])) continue;
            if ($r['source'] === 'subject_chooser' && !isset($chooser[$uid])) {
                $chooser[$uid] = json_decode((string)$r['payload'], true) ?: [];
            } elseif ($r['source'] === 'career_choice' && !isset($choice[$uid])) {
                $choice[$uid] = json_decode((string)$r['derived'], true) ?: [];
            } elseif ($r['source'] === 'job_fit') {
                $d = json_decode((string)$r['derived'], true) ?: [];
                $occ = (string)($d['occupation_id'] ?? '');
                if (!isset($jobFit[$uid][$occ])) $jobFit[$uid][$occ] = $d;
            }
        }
    }

    $warnings = array_map(fn($g) => $g + ['learners' => []], admin_warning_groups());
    $flagLearner = function (string $group, int $uid, string $detail) use (&$warnings, $learners) {
        $u = $learners[$uid];
        $warnings[$group]['learners'][] = [
            'name' => trim($u['firstName'] . ' ' . $u['lastName']), 'email' => $u['email'],
            'grade' => ADMIN_GRADE_LABELS[$u['grade']] ?? $u['grade'], 'detail' => $detail,
        ];
    };

    // --- Subject gaps (per learner, per intended career) -------------------
    $gaps = [];        // subject => [needed, not_taking, low_mark]
    $careerGap = [];   // uid => [career id => true] where a required subject is missing or under the mark
    foreach ($chooser as $uid => $p) {
        $locked = in_array($learners[$uid]['grade'], ADMIN_LOCKED_GRADES, true);
        $track  = (string)($p['maths_track'] ?? '');
        $taking = array_flip(array_merge((array)($p['subjects'] ?? []), $track !== '' ? [$track] : []));
        $marks  = (array)($p['marks'] ?? []);
        $needs = []; $missing = []; $low = []; $mathsCareers = [];
        foreach ((array)($p['intended_careers'] ?? []) as $cid) {
            $occ = $occupations[$cid] ?? null;
            if (!$occ) continue;
            if (($occ['math_track'] ?? '') === 'mathematics' && $track === 'Mathematical Literacy') $mathsCareers[] = $occ['title'];
            foreach ($occ['subject_requirements'] ?? [] as $req) {
                if ($req['necessity'] !== 'required') continue;
                $s = $req['subject']; $min = (int)($req['min_percent'] ?? 0);
                $needs[$s] = max($needs[$s] ?? 0, $min);
                if (!isset($taking[$s])) { $missing[$s] = true; $careerGap[$uid][$cid] = true; }
                elseif ($min > 0 && isset($marks[$s]) && (int)$marks[$s] < $min) { $low[$s] = "$s " . (int)$marks[$s] . "% (needs $min%)"; $careerGap[$uid][$cid] = true; }
            }
        }
        foreach ($needs as $s => $_) {
            $gaps[$s] ??= ['needed' => 0, 'not_taking' => 0, 'low_mark' => 0];
            $gaps[$s]['needed']++;
            if (isset($missing[$s])) $gaps[$s]['not_taking']++;
            elseif (isset($low[$s])) $gaps[$s]['low_mark']++;
        }
        if ($mathsCareers) $flagLearner('maths_lit', $uid, 'Wants ' . implode(', ', $mathsCareers));
        $otherMissing = array_diff(array_keys($missing), $mathsCareers ? ['Mathematics'] : []);
        if ($otherMissing) $flagLearner($locked ? 'locked_gap' : 'switch_now', $uid, 'Not taking ' . implode(', ', $otherMissing));
        if ($low) $flagLearner('low_marks', $uid, implode('; ', $low));
    }
    $subjectGaps = [];
    foreach ($gaps as $s => $g) {
        $short = $g['not_taking'] + $g['low_mark'];
        if ($short > 0) $subjectGaps[] = ['subject' => $s, 'short' => $short] + $g;
    }
    usort($subjectGaps, fn($a, $b) => $b['short'] <=> $a['short']);

    // --- Other warnings ----------------------------------------------------
    $cutoff = strtotime('-' . ADMIN_INACTIVE_DAYS . ' days');
    foreach ($learners as $uid => $u) {
        $careers = array_merge((array)($chooser[$uid]['intended_careers'] ?? []), (array)($choice[$uid]['top_careers'] ?? []));
        if (in_array($u['grade'], ADMIN_LOCKED_GRADES, true) && !$careers) $flagLearner('no_direction', $uid, isset($chooser[$uid]) || isset($jobFit[$uid]) ? 'Started, but no career picked' : 'No career picked yet');
        if (!isset($chooser[$uid]) && !isset($choice[$uid]) && !isset($jobFit[$uid]) && strtotime((string)$u['createdAt']) < $cutoff) {
            $flagLearner('inactive', $uid, 'Joined ' . date('j M Y', strtotime((string)$u['createdAt'])));
        }
        if (!empty($jobFit[$uid])) {
            $scores = array_map(fn($d) => (int)($d['overall'] ?? 0), $jobFit[$uid]);
            if (max($scores) < 50) $flagLearner('poor_fit', $uid, 'Best fit ' . max($scores) . '% across ' . count($scores) . ' career' . (count($scores) > 1 ? 's' : ''));
        }
    }
    $atRisk = [];
    foreach ($warnings as $g) if ($g['level'] === 'high') foreach ($g['learners'] as $l) $atRisk[$l['email']] = true;

    // --- Interest groups ---------------------------------------------------
    $names = kp_riasec_friendly_names();
    $types = array_fill_keys(array_keys($names), 0);
    foreach ($choice as $d) {
        $top = substr((string)($d['code'] ?? ''), 0, 1);
        if (isset($types[$top])) $types[$top]++;
    }
    $personality = [];
    foreach ($types as $letter => $n) $personality[] = ['label' => $names[$letter], 'letter' => $letter, 'learners' => $n];
    usort($personality, fn($a, $b) => $b['learners'] <=> $a['learners']);

    $chipGroups = null; // null = the column doesn't exist yet
    if ($hasInterests) {
        $chipGroups = [];
        foreach (kp_interest_groups() as $group => $chips) $chipGroups[$group] = ['learners' => 0, 'chips' => array_fill_keys(array_keys($chips), 0)];
        foreach ($learners as $u) {
            $inGroup = [];
            foreach ((array)json_decode((string)($u['interests'] ?? ''), true) as $v) {
                $label = is_string($v) ? kp_interest_label($v) : null;
                if ($label === null) continue;
                foreach ($chipGroups as $group => &$g) if (isset($g['chips'][$label])) { $g['chips'][$label]++; $inGroup[$group] = true; }
                unset($g);
            }
            foreach ($inGroup as $group => $_) $chipGroups[$group]['learners']++;
        }
        foreach ($chipGroups as &$g) { arsort($g['chips']); $g['chips'] = array_filter($g['chips']); }
        unset($g);
    }

    // Career fields: who is drawn to each field, and how many of those who PICKED a career
    // in it are missing what it requires.
    $fields = []; $interest = [];
    foreach ($learners as $uid => $_) {
        $ids = array_unique(array_merge((array)($chooser[$uid]['intended_careers'] ?? []), (array)($choice[$uid]['top_careers'] ?? [])));
        $seenField = []; $gapField = [];
        foreach ($ids as $cid) {
            if (!isset($occupations[$cid])) continue;
            $interest[$cid] = ($interest[$cid] ?? 0) + 1;
            $f = $occupations[$cid]['field'] ?? 'Other';
            $seenField[$f] = true;
            if (!empty($careerGap[$uid][$cid])) $gapField[$f] = true;
        }
        foreach ($seenField as $f => $_) {
            $fields[$f] ??= ['field' => $f, 'learners' => 0, 'with_gap' => 0];
            $fields[$f]['learners']++;
            if (isset($gapField[$f])) $fields[$f]['with_gap']++;
        }
    }
    usort($fields, fn($a, $b) => $b['learners'] <=> $a['learners']);

    // --- Demand vs learner interest ----------------------------------------
    $pipeline = [];
    foreach ($occupations as $id => $occ) {
        $pipeline[] = ['title' => $occ['title'], 'field' => $occ['field'] ?? '', 'demand' => $occ['demand'] ?? 'medium', 'learners' => $interest[$id] ?? 0];
    }
    $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($pipeline, fn($a, $b) => [$rank[$a['demand']] ?? 1, $a['learners']] <=> [$rank[$b['demand']] ?? 1, $b['learners']]);

    // --- Job Fit -----------------------------------------------------------
    $flags = []; $fits = []; $fitCount = 0;
    foreach ($jobFit as $perCareer) foreach ($perCareer as $d) {
        $fitCount++;
        foreach ((array)($d['flags'] ?? []) as $f) $flags[$f] = ($flags[$f] ?? 0) + 1;
        if (isset($d['overall'])) $fits[] = (int)$d['overall'];
    }
    arsort($flags);

    // --- Grades and activity -----------------------------------------------
    $grades = [];
    foreach (ADMIN_GRADE_LABELS as $key => $label) if (!empty($allGrades[$key])) $grades[$label] = $allGrades[$key];
    $new30 = count(array_filter($learners, fn($u) => strtotime((string)$u['createdAt']) >= strtotime('-30 days')));

    return [
        'grade'         => $grade,
        'learners'      => count($learners),
        'new_30'        => $new30,
        'at_risk'       => count($atRisk),
        'grades'        => $grades,
        'funnel'        => [
            'Registered'      => count($learners),
            'Subject Chooser' => count($chooser),
            'Career Choice'   => count($choice),
            'Job Fit'         => count($jobFit),
        ],
        'job_fit_count' => $fitCount,
        'avg_fit'       => $fits ? (int)round(array_sum($fits) / count($fits)) : null,
        'warnings'      => $warnings,
        'personality'   => $personality,
        'chip_groups'   => $chipGroups,
        'fields'        => $fields,
        'subject_gaps'  => $subjectGaps,
        'pipeline'      => $pipeline,
        'top_flags'     => array_slice($flags, 0, 6, true),
    ];
}
