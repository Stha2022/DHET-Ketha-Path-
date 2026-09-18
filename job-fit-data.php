<?php
/**
 * Khetha Path — Job Fit Questionnaire (can-you-actually-do-and-sustain-this,
 * not would-you-enjoy-it — that's Career Choice). Pure data/scoring, no
 * output. Reads occupation, work-value, work-context and aptitude records
 * straight from occupation-data.php (kp_occupations()) — the same
 * O*NET/GATB-shaped fields that file already earmarks for this tool —
 * rather than keeping a second copy.
 *
 * Four sub-scales, scored independently and never collapsed into one
 * opaque number. A, B and C all use the same 3-point No/Maybe/Yes scale
 * (jf_scale()) — same shared scale Career Choice uses — so a learner
 * never has to reorient between question styles:
 *   A. Work values   — importance rating per kp_work_value_keys(),
 *                       matched against how much the occupation rewards each.
 *   B. Work context   — "would this bother you" rating per
 *                       kp_work_context_keys() factor, weighed against how
 *                       intense that factor actually is in the job (a Yes
 *                       only matters if the job is high-intensity on that
 *                       factor).
 *   C. Aptitudes      — "are you good at this" self-rating per
 *                       kp_aptitude_keys(), weighed against the level the
 *                       occupation requires (overqualification is never
 *                       penalised, only shortfall).
 *   D. Constraints    — practical/access questions with no single "right"
 *                       answer; reported as its own checklist of flags
 *                       rather than folded into the numeric score, since a
 *                       funding or mobility barrier isn't a personality
 *                       mismatch and shouldn't be laundered into one.
 */

require_once __DIR__ . '/occupation-data.php';

function jf_work_value_blurbs(): array {
    return [
        'achievement'        => 'lets me see real results from my own effort',
        'independence'       => 'lets me work on my own and make my own calls',
        'recognition'        => 'gives me real chances to grow, advance and be recognised',
        'relationships'      => 'lets me help people and work with a team I get on with',
        'support'            => 'comes with good training, guidance and backup from management',
        'working_conditions' => 'pays reliably and gives me security, variety and decent conditions',
    ];
}

function jf_work_context_blurbs(): array {
    return [
        'physical' => 'physically demanding work — lifting, standing, manual effort',
        'shifts'   => 'shift work or irregular/night hours',
        'travel'   => 'travelling away from a fixed base regularly',
        'people'   => 'being around and working closely with people all day',
    ];
}

function jf_aptitude_blurbs(): array {
    return [
        'verbal'            => 'reading, writing and explaining things clearly',
        'numerical'         => 'working with numbers and calculations',
        'spatial'           => 'picturing shapes, layouts or objects in your head',
        'manual_dexterity'  => 'handling and manipulating tools or objects with your hands',
    ];
}

// Shared 3-point scale for all three rated sub-scales (values, context,
// aptitudes) — same wording Career Choice uses, so the whole app answers
// in one consistent way instead of a different scale per screen.
function jf_scale(): array {
    return [1 => 'No', 2 => 'Maybe', 3 => 'Yes'];
}

/**
 * @return array Field => [key => option label] for the practical constraints
 *   sub-scale. No option is scored as inherently "wrong" — see
 *   jf_score_constraints().
 */
function jf_constraint_fields(): array {
    return [
        'relocate' => [
            'question' => 'Could you relocate for study or work if you needed to?',
            'why' => "You've just told us what kind of work pulls you in and what you can actually deliver. This is where that gets tested against real life — some of the best-matching jobs only exist in certain places.",
            'options' => ['yes' => 'Yes, easily', 'nearby' => 'Only within my area', 'no' => "No, I need to stay where I am"],
        ],
        'transport' => [
            'question' => 'Do you have reliable transport to get to work, campus or training?',
            'why' => "A job can tick every box on paper and still be out of reach if you can't get to it day after day.",
            'options' => ['yes' => 'Yes', 'no' => 'No'],
        ],
        'funding' => [
            'question' => 'Would you need financial help (like NSFAS, a bursary or a loan) to get qualified for this?',
            'why' => "Being drawn to a career and being able to afford training for it are two different things — this just makes sure we flag it if funding matters for the path you're leaning towards.",
            'options' => ['yes' => 'Yes', 'no' => 'No'],
        ],
        'accommodations' => [
            'question' => 'Do you need any accommodations for a disability or health condition?',
            'why' => "This helps us tell you upfront if a role you're interested in is physically demanding, so you can plan for what you'd need rather than be surprised by it.",
            'options' => ['yes' => 'Yes', 'no' => 'No'],
        ],
        'study_time' => [
            'question' => 'Realistically, how much time can you commit right now — to training, studying or building experience?',
            'why' => "Even a job you're clearly suited for takes time to train into. This is about matching the path to the time you actually have, not the time you wish you had.",
            'options' => ['low' => 'A few hours a week', 'medium' => 'Moderate, part-time pace', 'high' => 'Full-time, most of my week'],
        ],
    ];
}

function jf_rag(int $fitScore): string {
    if ($fitScore >= 75) return 'green';
    if ($fitScore >= 45) return 'amber';
    return 'red';
}

/**
 * @param array $ratings key => 1-3 (jf_scale()), how important that value
 *   is to the learner.
 */
function jf_score_values(array $ratings, array $occValues): array {
    $keys = array_keys(kp_work_value_keys());
    $items = [];
    $sum = 0;
    foreach ($keys as $key) {
        $rating = (int)($ratings[$key] ?? 2);
        $user = (int)round((($rating - 1) / 2) * 100);
        $occ = (int)($occValues[$key] ?? 0);
        $fit = max(0, 100 - abs($user - $occ));
        $items[$key] = ['rating' => $rating, 'user' => $user, 'occupation' => $occ, 'fit' => $fit, 'rag' => jf_rag($fit)];
        $sum += $fit;
    }
    return ['score' => (int)round($sum / count($keys)), 'items' => $items];
}

/**
 * @param array $ratings key => 1-3 (jf_scale()), how much that factor
 *   would bother the learner (1=No..3=Yes).
 */
function jf_score_context(array $ratings, array $occContext): array {
    $keys = array_keys(kp_work_context_keys());
    $items = [];
    $sum = 0;
    foreach ($keys as $key) {
        $rating = (int)($ratings[$key] ?? 2);
        $problem = (($rating - 1) / 2) * 100;
        $intensity = (int)($occContext[$key] ?? 0);
        // Friction only bites where the job is actually intense on this
        // factor AND the learner would struggle with it — a "Yes" rating
        // on something the job barely involves shouldn't hurt the fit.
        $friction = ($intensity / 100) * ($problem / 100) * 100;
        $fit = max(0, 100 - $friction);
        $items[$key] = ['rating' => $rating, 'problem' => (int)round($problem), 'intensity' => $intensity, 'fit' => (int)round($fit), 'rag' => jf_rag((int)round($fit))];
        $sum += $fit;
    }
    return ['score' => (int)round($sum / count($keys)), 'items' => $items];
}

/**
 * @param array $ratings key => 1-3 (jf_scale()), learner's self-rating
 *   (1=No..3=Yes, "are you good at this").
 */
function jf_score_aptitudes(array $ratings, array $occAptitudes): array {
    $keys = array_keys(kp_aptitude_keys());
    $items = [];
    $sum = 0;
    foreach ($keys as $key) {
        $rating = (int)($ratings[$key] ?? 2);
        $user = (($rating - 1) / 2) * 100;
        $required = (int)($occAptitudes[$key] ?? 0);
        $shortfall = max(0, $required - $user);
        $fit = max(0, 100 - $shortfall);
        $items[$key] = ['rating' => $rating, 'user' => (int)round($user), 'required' => $required, 'fit' => (int)round($fit), 'rag' => jf_rag((int)round($fit))];
        $sum += $fit;
    }
    return ['score' => (int)round($sum / count($keys)), 'items' => $items];
}

/**
 * Not scored into the overall percentage — see file docblock. Each item
 * gets its own honest green/amber/red plus the sentence a learner needs,
 * checked against the occupation's own work context and training routes
 * where a real relationship exists in the data.
 */
function jf_score_constraints(array $constraints, array $occupation): array {
    $fields = jf_constraint_fields();
    $items = [];

    $travel = (int)($occupation['work_context']['travel'] ?? 0);
    $mobilityBlocked = ($constraints['relocate'] ?? '') === 'no' || ($constraints['transport'] ?? '') === 'no';
    if ($travel >= 50 && $mobilityBlocked) {
        $rag = 'red';
        $note = 'This role involves regular travel away from a fixed base. You said relocating or reliable transport would be difficult.';
    } elseif ($travel >= 50) {
        $rag = 'green';
        $note = "This role involves regular travel, and you said mobility isn't a barrier for you.";
    } else {
        $rag = 'green';
        $note = "This role doesn't demand much travel or relocation.";
    }
    $items['relocate'] = ['label' => 'Relocation & transport', 'rag' => $rag, 'note' => $note];

    $quals = kp_qualifications_for_occupation($occupation['id']);
    $providers = [];
    foreach ($quals as $q) foreach (kp_providers_for_qualification($q['id']) as $p) $providers[$p['id']] = $p;
    $anyNsfas = false;
    foreach ($providers as $p) if (!empty($p['nsfas_accredited'])) $anyNsfas = true;
    if (($constraints['funding'] ?? '') === 'yes') {
        if ($anyNsfas || empty($providers)) {
            $rag = 'green';
            $note = 'At least one training route for this career has NSFAS-accredited providers, so funding support may be available.';
        } else {
            $rag = 'amber';
            $note = 'None of the listed providers for this career are flagged NSFAS-accredited — check funding options carefully.';
        }
    } else {
        $rag = 'green';
        $note = "You said funding isn't a barrier for you.";
    }
    $items['funding'] = ['label' => 'Funding for study', 'rag' => $rag, 'note' => $note];

    $physical = (int)($occupation['work_context']['physical'] ?? 0);
    $risk = (int)($occupation['work_context']['risk'] ?? 0);
    if (($constraints['accommodations'] ?? '') === 'yes' && ($physical >= 60 || $risk >= 60)) {
        $rag = 'amber';
        $note = 'This role is physically demanding' . ($risk >= 60 ? ' and involves hazard exposure' : '') . '. Talk to the employer or institution early about what accommodations are possible.';
    } elseif (($constraints['accommodations'] ?? '') === 'yes') {
        $rag = 'green';
        $note = "This role isn't especially physically demanding, but it's still worth confirming accommodations with the employer or institution.";
    } else {
        $rag = 'green';
        $note = "You said you don't need specific accommodations.";
    }
    $items['accommodations'] = ['label' => 'Accommodations', 'rag' => $rag, 'note' => $note];

    $longestYears = 0;
    foreach ($quals as $q) {
        if (preg_match('/(\d+)/', $q['duration'] ?? '', $m)) $longestYears = max($longestYears, (int)$m[1]);
    }
    if (($constraints['study_time'] ?? '') === 'low' && $longestYears >= 3) {
        $rag = 'red';
        $note = "The typical training route here runs about {$longestYears}+ years. You said you only have a little time available for that right now.";
    } elseif ($longestYears > 0) {
        $rag = 'green';
        $note = "The typical training route here runs about {$longestYears} year" . ($longestYears === 1 ? '' : 's') . ", which lines up with the time you said you can commit.";
    } else {
        $rag = 'green';
        $note = 'No fixed training duration on record for this route.';
    }
    $items['study_time'] = ['label' => 'Time available', 'rag' => $rag, 'note' => $note];

    // Attach the question text/labels the UI needs alongside each verdict.
    foreach ($items as $key => &$item) $item['answer'] = $fields[$key]['options'][$constraints[$key] ?? ''] ?? '—';
    unset($item);

    return $items;
}

/**
 * Full breakdown for one occupation. Never collapses to a single number —
 * $overall sits alongside every sub-score, and $flags carries the specific,
 * honest mismatch sentences (e.g. "This job involves irregular night
 * shifts. You said that would be a problem.") rather than a verdict alone.
 */
function jf_score_occupation(array $answers, array $occupation): array {
    $values     = jf_score_values($answers['value_rating'] ?? [], $occupation['work_values']);
    $context    = jf_score_context($answers['context'] ?? [], $occupation['work_context']);
    $aptitudes  = jf_score_aptitudes($answers['aptitude'] ?? [], $occupation['aptitudes']);
    $constraints = jf_score_constraints($answers['constraints'] ?? [], $occupation);

    $overall = (int)round(($values['score'] + $context['score'] + $aptitudes['score']) / 3);

    // Everything worth a second look, across all four sub-scales — shown
    // directly on the results card (see occupation.php), not tucked away
    // behind the "Full breakdown" accordion. Amber counts here too, not
    // just Red: a lighter mismatch still deserves to be seen without
    // having to open anything.
    $flags = [];
    $valueLabels = kp_work_value_keys();
    foreach ($values['items'] as $key => $item) {
        if ($item['rag'] !== 'green') $flags[] = $valueLabels[$key] . ' matters to you, but this job doesn\'t offer much of it.';
    }
    $contextBlurbs = jf_work_context_blurbs();
    foreach ($context['items'] as $key => $item) {
        if ($item['rag'] !== 'green') $flags[] = 'This job involves ' . $contextBlurbs[$key] . '. You said that would bother you.';
    }
    $aptitudeBlurbs = jf_aptitude_blurbs();
    foreach ($aptitudes['items'] as $key => $item) {
        if ($item['rag'] !== 'green') $flags[] = 'This job leans heavily on ' . $aptitudeBlurbs[$key] . ' — an area you rated yourself lower on.';
    }
    foreach ($constraints as $item) {
        if ($item['rag'] !== 'green') $flags[] = $item['note'];
    }

    return [
        'occupation_id' => $occupation['id'], 'title' => $occupation['title'], 'field' => $occupation['field'],
        'overall' => $overall,
        'values' => $values, 'context' => $context, 'aptitudes' => $aptitudes, 'constraints' => $constraints,
        'flags' => $flags,
    ];
}

/**
 * Broad mode: score every occupation in the directory and rank by overall
 * fit. @return array Sorted best-first, each entry shaped like
 *   jf_score_occupation()'s return.
 */
function jf_match_occupations(array $answers): array {
    $results = [];
    foreach (kp_occupations() as $occ) $results[] = jf_score_occupation($answers, $occ);
    usort($results, fn($a, $b) => $b['overall'] <=> $a['overall']);
    return $results;
}

/**
 * Validates and normalises raw $_POST data for the questionnaire.
 * @return array{answers: array, errors: array}
 */
function jf_parse_answers(array $post): array {
    $errors = [];

    $valueRating = [];
    foreach (array_keys(kp_work_value_keys()) as $key) {
        $val = (int)($post['value_rating'][$key] ?? 0);
        if ($val < 1 || $val > 3) { $errors['value_rating'] = 'Answer every work-values question.'; break; }
        $valueRating[$key] = $val;
    }

    $context = [];
    foreach (array_keys(kp_work_context_keys()) as $key) {
        $val = (int)($post['context'][$key] ?? 0);
        if ($val < 1 || $val > 3) { $errors['context'] = 'Answer every work-context question.'; break; }
        $context[$key] = $val;
    }

    $aptitude = [];
    foreach (array_keys(kp_aptitude_keys()) as $key) {
        $val = (int)($post['aptitude'][$key] ?? 0);
        if ($val < 1 || $val > 3) { $errors['aptitude'] = 'Rate every aptitude.'; break; }
        $aptitude[$key] = $val;
    }

    $constraintFields = jf_constraint_fields();
    $constraints = [];
    foreach ($constraintFields as $key => $field) {
        $val = $post['constraints'][$key] ?? '';
        if (!array_key_exists($val, $field['options'])) { $errors['constraints'] = 'Answer every practical question.'; break; }
        $constraints[$key] = $val;
    }

    return [
        'answers' => ['value_rating' => $valueRating, 'context' => $context, 'aptitude' => $aptitude, 'constraints' => $constraints],
        'errors' => $errors,
    ];
}
