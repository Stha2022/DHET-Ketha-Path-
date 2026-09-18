<?php
/**
 * Khetha Path — Subject Chooser deterministic rules engine.
 * Pure data/scoring, no output.
 *
 * Occupation data is no longer authored here — sj_occupations() adapts
 * the canonical records in occupation-data.php (kp_occupations()) into
 * the required/recommended/min_marks shape this engine expects. To add
 * or correct an occupation, edit occupation-data.php; this file and the
 * UI that consumes it don't need to change.
 */

require_once __DIR__ . '/occupation-data.php';

function sj_languages(): array {
    return ['English', 'Afrikaans', 'isiZulu', 'isiXhosa', 'Sepedi', 'Setswana', 'Sesotho', 'Xitsonga', 'siSwati', 'Tshivenda', 'isiNdebele'];
}

// The three CAPS maths-track options — mutually exclusive, one is compulsory.
function sj_maths_tracks(): array {
    return ['Mathematics', 'Mathematical Literacy', 'Technical Mathematics'];
}

// CAPS elective subjects a school might offer (the "offered" pool in step 4).
// Maths tracks and Life Orientation are handled separately — they are not
// elective slots.
function sj_caps_electives(): array {
    return [
        'Physical Sciences', 'Life Sciences', 'Accounting', 'Business Studies', 'Economics',
        'Geography', 'History', 'Tourism', 'Consumer Studies', 'Agricultural Sciences',
        'Information Technology', 'Computer Applications Technology', 'Engineering Graphics & Design',
        'Visual Arts', 'Dramatic Arts', 'Music', 'Design', 'Technical Sciences', 'Hospitality Studies',
    ];
}

function sj_grades(): array {
    return [
        'Grade 9'  => 'Advisory — you\'ll finalise subjects for Grade 10. Use this to see what different combinations open up.',
        'Grade 10' => 'This is usually your last real window to change subjects — use this to check you\'re on track while you still can.',
        'Grade 11' => 'Your subjects are largely set now. This runs in diagnostic mode — what your current subjects support, and alternative pathways for what they don\'t.',
        'Grade 12' => 'Your subjects are locked in. This runs in diagnostic mode — what your current subjects support, and alternative pathways for what they don\'t.',
    ];
}

/**
 * Adapts kp_occupations() (occupation-data.php) into the
 * label/field/math_track/required/recommended/min_marks/pathways/riasec
 * shape this engine and subject.php's UI expect. required = 'required'
 * subject_requirements with no numeric bar dropped by min_marks (still
 * counted as required, just nothing to check the mark against);
 * recommended = 'recommended' entries. riasec is rescaled from
 * occupation-data.php's 0-100 to this engine's 0-1.
 * @return array<string, array>
 */
function sj_occupations(): array {
    $out = [];
    foreach (kp_occupations() as $key => $occ) {
        $required = [];
        $recommended = [];
        $minMarks = [];
        foreach ($occ['subject_requirements'] as $req) {
            if ($req['necessity'] === 'required') {
                $required[] = $req['subject'];
                if ($req['min_percent'] !== null) $minMarks[$req['subject']] = $req['min_percent'];
            } else {
                $recommended[] = $req['subject'];
            }
        }

        $riasec = [];
        foreach ($occ['riasec'] as $dim => $val) $riasec[$dim] = $val / 100;

        $out[$key] = [
            'label' => $occ['title'], 'field' => $occ['field'],
            'math_track' => $occ['math_track'],
            'required' => $required, 'recommended' => $recommended, 'min_marks' => $minMarks,
            'pathways' => $occ['pathways'], 'riasec' => $riasec,
        ];
    }
    return $out;
}

// Marks bands from the input screen, mapped to a representative midpoint %.
function sj_marks_bands(): array {
    return [
        '<30' => 25, '30-39' => 35, '40-49' => 45, '50-59' => 55,
        '60-69' => 65, '70-79' => 75, '80+' => 85,
    ];
}

// The marks band whose midpoint is nearest a saved mark (the profile stores
// midpoints, the wizard's <select> works in bands). Ties go to the lower band.
function sj_band_for_mark(int $mark): string {
    $best = '';
    $bestGap = PHP_INT_MAX;
    foreach (sj_marks_bands() as $band => $midpoint) {
        $gap = abs($midpoint - $mark);
        if ($gap < $bestGap) { $best = $band; $bestGap = $gap; }
    }
    return $best;
}

/**
 * Plain OPEN / CLOSED for one occupation against the learner's current
 * subjects and Maths track, with a one-line reason.
 *
 * CLOSED = the Maths track hard-blocks it, or a required subject isn't
 * taken (the same test sj_forward_package() uses for "keeps open"). Marks
 * never close a career here — a mark under the bar only adds a warning to an
 * OPEN reason, since marks can still change.
 *
 * @param array $occ One entry from sj_occupations()
 * @return array{open: bool, reason: string, connected: string[]}
 */
function sj_open_status(array $selectedSubjects, array $marks, string $mathsTrack, array $occ): array {
    $has = fn(string $s) => in_array($s, $selectedSubjects, true) || $s === $mathsTrack;
    $needs = fn(string $s) => $s . (isset($occ['min_marks'][$s]) ? ' (min ' . $occ['min_marks'][$s] . '%)' : '');

    $mathsBlocked = $occ['math_track'] === 'mathematics' && $mathsTrack !== 'Mathematics';
    $missing = [];
    foreach ($occ['required'] as $s) {
        if ($mathsBlocked && $s === 'Mathematics') continue; // said in the Maths clause instead
        if (!$has($s)) $missing[] = $s;
    }

    if ($mathsBlocked || $missing) {
        $names = array_map($needs, $missing);
        $mathsClause = 'needs ' . $needs('Mathematics') . ', you take ' . ($mathsTrack !== '' ? $mathsTrack : 'no Maths track yet');
        if ($mathsBlocked && $names) {
            $reason = 'Closed: ' . $mathsClause . '; also needs ' . sj_join_and($names);
        } elseif ($mathsBlocked) {
            $reason = 'Closed: ' . $mathsClause;
        } else {
            $reason = 'Closed: needs ' . sj_join_and($names) . ", you don't take " . (count($names) === 1 ? 'it' : 'them');
        }
        return ['open' => false, 'reason' => $reason, 'connected' => []];
    }

    $connected = [];
    foreach (array_merge($occ['required'], $occ['recommended']) as $s) {
        if ($has($s) && !in_array($s, $connected, true)) $connected[] = $s;
    }
    if ($connected) {
        $reason = 'Connected through ' . implode(', ', $connected);
    } else {
        $reason = 'Open: no subjects required' . ($occ['recommended'] ? ' (recommended: ' . implode(', ', $occ['recommended']) . ')' : '');
    }

    $gaps = [];
    foreach ($occ['min_marks'] as $s => $min) {
        if ($has($s) && isset($marks[$s]) && $marks[$s] < $min) $gaps[] = "$s needs $min%, you have about {$marks[$s]}%";
    }
    if ($gaps) $reason .= '. Watch your marks: ' . implode('; ', $gaps);

    return ['open' => true, 'reason' => $reason, 'connected' => $connected];
}

// "A", "A and B", "A, B and C".
function sj_join_and(array $items): string {
    $last = array_pop($items);
    return $items ? implode(', ', $items) . ' and ' . $last : (string)$last;
}

/**
 * Occupations hard-closed by maths-track choice alone — meaningful even
 * before any marks/subjects are known, so it can be shown right after
 * step 3.
 * @return array List of ['key','label','field']
 */
function sj_maths_track_closes(string $mathsTrack): array {
    $closed = [];
    foreach (sj_occupations() as $key => $occ) {
        if ($occ['math_track'] === 'mathematics' && $mathsTrack !== 'Mathematics') {
            $closed[] = ['key' => $key, 'label' => $occ['label'], 'field' => $occ['field']];
        }
    }
    return $closed;
}

/**
 * @param array $selectedSubjects Subject names the learner currently takes (with a mark given)
 * @param array $marks [subject => mark %]
 * @param string $mathsTrack
 * @param array $occ One entry from sj_occupations()
 * @return array{fit: float, hardFail: bool, requiredMet: int, requiredTotal: int, recommendedMet: int, recommendedTotal: int, gaps: array}
 */
function sj_score_occupation(array $selectedSubjects, array $marks, string $mathsTrack, array $occ): array {
    $hardFail = $occ['math_track'] === 'mathematics' && $mathsTrack !== 'Mathematics';

    $requiredTotal = count($occ['required']);
    $requiredMet = 0;
    $gaps = [];
    foreach ($occ['required'] as $subj) {
        $has = in_array($subj, $selectedSubjects, true) || $subj === $mathsTrack || ($subj === 'Mathematics' && $mathsTrack === 'Mathematics');
        if ($has) {
            $requiredMet++;
            $minMark = $occ['min_marks'][$subj] ?? null;
            $mark = $marks[$subj] ?? null;
            if ($minMark !== null && $mark !== null && $mark < $minMark) {
                $gaps[] = ['subject' => $subj, 'needs' => $minMark, 'has' => $mark];
            }
        }
    }

    $recommendedTotal = count($occ['recommended']);
    $recommendedMet = 0;
    foreach ($occ['recommended'] as $subj) {
        if (in_array($subj, $selectedSubjects, true)) $recommendedMet++;
    }

    // Marks margin: average, across required subjects with both a pass mark
    // and a learner mark, of how far above/below the bar they are (0-1,
    // clipped). Neutral (0.5) when no marks are available to compare.
    $marginSamples = [];
    foreach ($occ['min_marks'] as $subj => $minMark) {
        if (isset($marks[$subj])) {
            $margin = ($marks[$subj] - $minMark + 30) / 60; // +/-30% band mapped to 0-1
            $marginSamples[] = max(0, min(1, $margin));
        }
    }
    $marksMargin = $marginSamples ? array_sum($marginSamples) / count($marginSamples) : 0.5;

    $fit = $requiredTotal > 0
        ? 0.70 * ($requiredMet / $requiredTotal) + 0.20 * ($recommendedTotal > 0 ? $recommendedMet / $recommendedTotal : 1) + 0.10 * $marksMargin
        : 0.70 * 1 + 0.20 * ($recommendedTotal > 0 ? $recommendedMet / $recommendedTotal : 1) + 0.10 * $marksMargin;

    return [
        'fit' => $hardFail ? 0.0 : round($fit, 3),
        'hardFail' => $hardFail,
        'requiredMet' => $requiredMet, 'requiredTotal' => $requiredTotal,
        'recommendedMet' => $recommendedMet, 'recommendedTotal' => $recommendedTotal,
        'gaps' => $gaps,
    ];
}

function sj_bucket(float $fit, bool $hardFail): string {
    if ($hardFail) return 'closed';
    if ($fit >= 0.85) return 'open';
    if ($fit >= 0.60) return 'effort';
    return 'closed';
}

/**
 * Reverse direction: score every occupation against the learner's current
 * subjects/marks/maths track.
 * @return array Ranked list of ['key','label','field','fit','bucket','gaps','hardFail']
 */
function sj_reverse_scan(array $selectedSubjects, array $marks, string $mathsTrack): array {
    $results = [];
    foreach (sj_occupations() as $key => $occ) {
        $s = sj_score_occupation($selectedSubjects, $marks, $mathsTrack, $occ);
        $results[] = [
            'key' => $key, 'label' => $occ['label'], 'field' => $occ['field'],
            'fit' => $s['fit'], 'bucket' => sj_bucket($s['fit'], $s['hardFail']),
            'hardFail' => $s['hardFail'], 'gaps' => $s['gaps'],
            'pathways' => $occ['pathways'],
        ];
    }
    usort($results, fn($a, $b) => $b['fit'] <=> $a['fit']);
    return $results;
}

/**
 * "What if" subject swap — for Grade 11/12 learners whose subjects are
 * already fixed, rather than choosing fresh. Drops one current subject
 * (and optionally adds a replacement, with no mark yet since it's
 * hypothetical), then compares which occupations' buckets improve or
 * worsen against the current picture.
 *
 * @return array{opened: array, closed: array, after: array}
 *   opened/closed are lists of ['key','label','field']; after is the
 *   full reverse scan with the swap applied.
 */
function sj_swap_effect(array $selectedSubjects, array $marks, string $mathsTrack, string $dropSubject, string $addSubject): array {
    $before = sj_reverse_scan($selectedSubjects, $marks, $mathsTrack);

    $afterSubjects = array_values(array_diff($selectedSubjects, [$dropSubject]));
    $afterMarks = $marks;
    unset($afterMarks[$dropSubject]);
    if ($addSubject !== '' && !in_array($addSubject, $afterSubjects, true)) {
        $afterSubjects[] = $addSubject;
    }
    $after = sj_reverse_scan($afterSubjects, $afterMarks, $mathsTrack);

    $beforeBucket = [];
    foreach ($before as $r) $beforeBucket[$r['key']] = $r['bucket'];

    $rank = ['closed' => 0, 'effort' => 1, 'open' => 2];
    $occupations = sj_occupations();
    $opened = [];
    $closed = [];
    foreach ($after as $r) {
        $was = $beforeBucket[$r['key']] ?? 'closed';
        if ($rank[$r['bucket']] > $rank[$was]) {
            $opened[] = ['key' => $r['key'], 'label' => $r['label'], 'field' => $r['field']];
        } elseif ($rank[$r['bucket']] < $rank[$was]) {
            $closed[] = ['key' => $r['key'], 'label' => $r['label'], 'field' => $r['field']];
        }
    }

    return ['opened' => $opened, 'closed' => $closed, 'after' => $after];
}

/**
 * Forward direction: union the required/recommended subjects of the
 * learner's 1-3 intended occupations into a 7-subject CAPS package
 * (4 compulsory + 3 elective slots). Assumes every CAPS elective is
 * available at the learner's school — there's no "what does your school
 * offer" question in this wizard, so this isn't gated on which subjects
 * the learner happens to already be taking (see subject.php: that's a
 * separate concept used only for the reverse/diagnostic scan). If
 * required electives exceed 3 slots, the conflict is surfaced rather
 * than silently dropping a subject.
 *
 * @param array $occupationKeys 1-3 keys into sj_occupations()
 * @param string $mathsTrack
 * @param string $hl Home Language
 * @param string $fal First Additional Language
 * @return array{compulsory: array, requiredElectives: array, recommendedElectives: array, package: array, conflict: bool, opensCount: int}
 */
function sj_forward_package(array $occupationKeys, string $mathsTrack, string $hl, string $fal): array {
    $occupations = sj_occupations();
    $requiredElectives = [];
    $recommendedElectives = [];

    foreach ($occupationKeys as $key) {
        if (!isset($occupations[$key])) continue;
        $occ = $occupations[$key];
        foreach ($occ['required'] as $subj) {
            if ($subj === 'Mathematics') continue; // covered by the maths-track compulsory slot
            if (!in_array($subj, $requiredElectives, true)) $requiredElectives[] = $subj;
        }
        foreach ($occ['recommended'] as $subj) {
            if (!in_array($subj, $requiredElectives, true) && !in_array($subj, $recommendedElectives, true)) {
                $recommendedElectives[] = $subj;
            }
        }
    }

    $conflict = count($requiredElectives) > 3;
    $package = array_slice($requiredElectives, 0, 3);
    $slotsLeft = 3 - count($package);
    if ($slotsLeft > 0) {
        $package = array_merge($package, array_slice($recommendedElectives, 0, $slotsLeft));
    }

    // Count how many of the chosen occupations this package (plus maths
    // track) would keep open (not hard-closed, all required subjects present).
    $opensCount = 0;
    foreach ($occupationKeys as $key) {
        if (!isset($occupations[$key])) continue;
        $s = sj_score_occupation($package, [], $mathsTrack, $occupations[$key]);
        if (!$s['hardFail'] && $s['requiredMet'] === $s['requiredTotal']) $opensCount++;
    }

    return [
        'compulsory' => [$hl, $fal, $mathsTrack, 'Life Orientation'],
        'requiredElectives' => $requiredElectives,
        'recommendedElectives' => $recommendedElectives,
        'package' => $package,
        'conflict' => $conflict,
        'opensCount' => $opensCount,
    ];
}
