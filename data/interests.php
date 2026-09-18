<?php
/**
 * Khetha Path — interest chips and their RIASEC codes.
 *
 * Each chip carries one or two RIASEC codes (the same six dimensions the
 * Career Choice quiz scores). The FIRST code is the chip's main pull, the
 * second a lesser one. A chip's value is its label, e.g. 'Solving puzzles'.
 * Pure data/scoring, no output.
 */

/**
 * @return array<string, array<string, string[]>> Group label => [chip label => RIASEC codes].
 */
function kp_interest_groups(): array {
    return [
        'Create and express' => [
            'Arts and culture' => ['A'],
            'Design and style' => ['A'],
            'Music and performance' => ['A'],
            'Writing and stories' => ['A', 'S'],
            'Building and making' => ['R', 'A'],
            'Making things' => ['R'],
        ],
        'Help and connect' => [
            'Helping people' => ['S'],
            'Working with people' => ['S', 'E'],
            'Teaching others' => ['S'],
            'Leading a team' => ['E'],
            'Deep conversations' => ['S'],
            'Understanding people' => ['S', 'I'],
        ],
        'Think and discover' => [
            'Technology' => ['I', 'R'],
            'Solving puzzles' => ['I'],
            'Asking questions' => ['I'],
            'Business and money' => ['E', 'C'],
            'Making plans' => ['C', 'E'],
            'Science and discovery' => ['I'],
            'Keeping things organised' => ['C'],
        ],
        'Outdoors and hands-on' => [
            'Nature and animals' => ['R', 'I'],
            'Working outdoors' => ['R'],
            'Sport and movement' => ['R'],
            'Working with animals' => ['R', 'S'],
            'Building and fixing' => ['R'],
            'Exploring places' => ['R'],
        ],
    ];
}

/** Plain-language names for the six RIASEC letters, for sentences shown to learners. */
function kp_riasec_friendly_names(): array {
    return [
        'R' => 'Hands-on', 'I' => 'Investigative', 'A' => 'Creative',
        'S' => 'Helping', 'E' => 'Enterprising', 'C' => 'Organised',
    ];
}

/** @return array<string, string[]> Chip label => RIASEC codes, across all groups. */
function kp_interest_chips(): array {
    $flat = [];
    foreach (kp_interest_groups() as $chips) $flat += $chips;
    return $flat;
}

/** The chip label for a saved value (case-insensitive), or null if it isn't a chip. */
function kp_interest_label(string $value): ?string {
    static $byLower = null;
    if ($byLower === null) {
        $byLower = [];
        foreach (array_keys(kp_interest_chips()) as $label) $byLower[mb_strtolower($label)] = $label;
    }
    return $byLower[mb_strtolower(trim($value))] ?? null;
}

/**
 * RIASEC weights from a learner's interests: +2 for each chip's first code,
 * +1 for its second. Values that aren't chips (e.g. free text typed at
 * registration) are ignored, and a chip counts once however often it appears.
 * @param string[] $interests Chip labels.
 * @return array<string, int> Dimension => points, highest first; only dimensions above 0.
 */
function kp_interests_to_riasec(array $interests): array {
    $chips = kp_interest_chips();
    $seen = [];
    $riasec = [];
    foreach ($interests as $value) {
        $label = is_string($value) ? kp_interest_label($value) : null;
        if ($label === null || isset($seen[$label])) continue;
        $seen[$label] = true;
        foreach ($chips[$label] as $i => $code) $riasec[$code] = ($riasec[$code] ?? 0) + ($i === 0 ? 2 : 1);
    }
    arsort($riasec); // stable: equal scores keep the order chips introduced them
    return $riasec;
}
