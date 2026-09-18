<?php
/**
 * Khetha Path — Career Choice questionnaire (RIASEC / Holland Code).
 * Pure data/scoring, no output. Occupation matching reuses the same
 * directory as Subject Chooser (sj_occupations() in subject-data.php,
 * each entry extended with a 'riasec' vector) so results can be grouped
 * by subject-profile reachability when that quiz has already been run.
 *
 * RIASEC dimension assignments for the mock occupations below are
 * grounded in published O*NET/Holland occupational codes (e.g. Software
 * Development = Investigative-led, Civil Engineering/Electrician =
 * Realistic-led, Accounting = Conventional-led, Law = Enterprising-led,
 * Graphic Design = Artistic-led) rather than invented from scratch —
 * still an approximation standing in for a real, validated instrument.
 */

require_once __DIR__ . '/subject-data.php';

function cq_dimensions(): array {
    return [
        'R' => 'Realistic',
        'I' => 'Investigative',
        'A' => 'Artistic',
        'S' => 'Social',
        'E' => 'Enterprising',
        'C' => 'Conventional',
    ];
}

/**
 * 30 SA-contextual items, 5 per dimension, interleaved R,I,A,S,E,C.
 *
 * Career quiz items
 * @return array Flat list of ['id'=>int,'dim'=>string,'text'=>string]
 */
function cq_items(): array {
    $byDim = [
        'R' => [
            'Repair a car engine',
            'Fix a leaking tap or geyser',
            'Build a chicken coop or fence',
            'Rewire a plug or light switch',
            "Service a taxi's brakes",
        ],
        'I' => [
            'Work out why a machine stopped working',
            'Figure out why load shedding schedules keep changing',
            'Test soil to see what crops would grow best',
            "Investigate why a shop's stock keeps going missing",
            'Research why a river has become polluted',
        ],
        'A' => [
            'Design a poster for a local event',
            'Paint a mural on a community wall',
            'Write and perform a poem or rap',
            'Design an outfit for a traditional ceremony',
            "Take photos for a friend's social media page",
        ],
        'S' => [
            'Teach someone in your community to read',
            'Tutor a younger learner after school',
            'Counsel a friend going through a hard time',
            'Organise a food drive for your community',
            'Care for elderly neighbours',
        ],
        'E' => [
            'Run your own spaza shop',
            'Start a stokvel and manage its finances',
            'Negotiate a better price at a market',
            'Convince investors to fund your idea',
            "Lead a group project and assign everyone's tasks",
        ],
        'C' => [
            'Keep records of money coming in and going out',
            'Organise a filing system for a small business',
            "Balance a stokvel's books at month end",
            'Capture attendance registers for a school',
            'Check invoices against delivery notes',
        ],
    ];

    $dims = array_keys(cq_dimensions());
    $items = [];
    $id = 0;
    for ($round = 0; $round < 5; $round++) {
        foreach ($dims as $d) {
            $items[] = ['id' => $id, 'dim' => $d, 'text' => $byDim[$d][$round]];
            $id++;
        }
    }
    return $items;
}

/**
 * @param array $answers [itemId => rating 1-3 (No/Maybe/Yes)], one per
 *   cq_items() entry
 * @return array{scores: array, vector: array, top3: array, code: string}
 */
function cq_score(array $answers): array {
    $items = cq_items();
    $byId = [];
    foreach ($items as $it) $byId[$it['id']] = $it;

    $dims = array_fill_keys(array_keys(cq_dimensions()), []);
    foreach ($answers as $id => $rating) {
        $id = (int)$id;
        $rating = (int)$rating;
        if (!isset($byId[$id]) || $rating < 1 || $rating > 3) continue;
        $dims[$byId[$id]['dim']][] = $rating;
    }

    $scores = [];
    $vector = [];
    foreach ($dims as $dim => $ratings) {
        $n = count($ratings);
        $avg = $n > 0 ? array_sum($ratings) / $n : 0;
        $normalized = $n > 0 ? (int)round((($avg - 1) / 2) * 100) : 0;
        $scores[$dim] = ['score' => $normalized, 'itemsAnswered' => $n];
        $vector[$dim] = $normalized / 100;
    }

    $byScore = $scores;
    uasort($byScore, fn($a, $b) => $b['score'] <=> $a['score']);
    $top3 = array_slice(array_keys($byScore), 0, 3);

    return ['scores' => $scores, 'vector' => $vector, 'top3' => $top3, 'code' => implode('', $top3)];
}

/**
 * Ranks occupations (from sj_occupations()) by cosine similarity between
 * the learner's RIASEC vector and each occupation's own vector.
 * @param array $userVector [dim => 0-1]
 * @return array Ranked list of ['key','label','field','match','reason']
 */
function cq_match_occupations(array $userVector): array {
    $labels = cq_dimensions();
    $dimKeys = array_keys($labels);
    $userTop = $userVector;
    arsort($userTop);
    $userTop2 = array_slice(array_keys($userTop), 0, 2);

    $results = [];
    foreach (sj_occupations() as $key => $occ) {
        if (!isset($occ['riasec'])) continue;
        $occVec = $occ['riasec'];

        $dot = 0; $userMag = 0; $occMag = 0;
        foreach ($dimKeys as $d) {
            $u = $userVector[$d] ?? 0;
            $o = $occVec[$d] ?? 0;
            $dot += $u * $o;
            $userMag += $u * $u;
            $occMag += $o * $o;
        }
        $cos = ($userMag > 0 && $occMag > 0) ? $dot / (sqrt($userMag) * sqrt($occMag)) : 0;

        $occTop = $occVec;
        arsort($occTop);
        $occTop2 = array_slice(array_keys($occTop), 0, 2);
        $overlap = array_values(array_intersect($userTop2, $occTop2));
        $reasonDims = !empty($overlap) ? $overlap : $userTop2;
        $reason = 'High ' . implode(' + ', array_map(fn($d) => $labels[$d], $reasonDims));

        $results[] = [
            'key' => $key, 'label' => $occ['label'], 'field' => $occ['field'],
            'match' => (int)round($cos * 100), 'reason' => $reason,
        ];
    }

    usort($results, fn($a, $b) => $b['match'] <=> $a['match']);
    return $results;
}

// Short word instead of a raw cosine-similarity percentage for results
// display — a % reads as more precise than this approximation actually is.
function cq_match_label(int $match): string {
    return $match >= 80 ? 'Strong match' : ($match >= 60 ? 'Good match' : 'Possible match');
}
