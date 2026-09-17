<?php
/**
 * Khetha Path — Subject Choice questionnaire data + scoring.
 * Combines the Interests, Career Abilities and Employability Skills
 * questionnaires (NCAP's Self Exploration set) into ranked study-field
 * suggestions. Pure data/scoring only — no session access, no output —
 * so the real NCAP question wording/weights can be swapped in later
 * without touching any page logic.
 */

// The 6 study fields subject choice is scored against, each with a
// handful of real matric subjects that lead into that field, plus a
// few illustrative institutions/qualifications in that field. The
// institution entries are MOCK placeholder data for this prototype —
// stand-ins for what a live NCAP course-finder lookup would return —
// not a live or verified programme listing.
function sc_categories(): array {
    return [
        'stem' => [
            'label'    => 'Science, Engineering & Technology',
            'subjects' => ['Mathematics', 'Physical Sciences', 'Life Sciences', 'Information Technology'],
            'institutions' => [
                ['name' => 'University of Pretoria', 'qualification' => 'BEng (various streams)'],
                ['name' => 'University of the Witwatersrand', 'qualification' => 'BSc Computer Science'],
                ['name' => 'Tshwane University of Technology', 'qualification' => 'National Diploma: Engineering'],
            ],
        ],
        'creative' => [
            'label'    => 'Creative Arts & Design',
            'subjects' => ['Visual Arts', 'Design', 'Dramatic Arts', 'Music'],
            'institutions' => [
                ['name' => 'University of Johannesburg', 'qualification' => 'BA Visual Art'],
                ['name' => 'Cape Peninsula University of Technology', 'qualification' => 'Diploma: Graphic Design'],
                ['name' => 'AFDA', 'qualification' => 'BA Motion Picture Medium'],
            ],
        ],
        'social' => [
            'label'    => 'Social Sciences & Humanities',
            'subjects' => ['History', 'Geography', 'Languages', 'Life Orientation'],
            'institutions' => [
                ['name' => 'University of Cape Town', 'qualification' => 'BSocSci'],
                ['name' => 'University of the Western Cape', 'qualification' => 'BA Humanities'],
                ['name' => 'University of South Africa (UNISA)', 'qualification' => 'BA (distance)'],
            ],
        ],
        'business' => [
            'label'    => 'Business & Commerce',
            'subjects' => ['Business Studies', 'Economics', 'Accounting', 'Mathematical Literacy'],
            'institutions' => [
                ['name' => 'University of Pretoria', 'qualification' => 'BCom (various streams)'],
                ['name' => 'University of Johannesburg', 'qualification' => 'Diploma: Marketing'],
                ['name' => 'Tshwane University of Technology', 'qualification' => 'National Diploma: Business Management'],
            ],
        ],
        'technical' => [
            'label'    => 'Technical & Artisan Trades',
            'subjects' => ['Engineering Graphics & Design', 'Technical Sciences', 'Technical Mathematics', 'Electrical Technology'],
            'institutions' => [
                ['name' => 'False Bay TVET College', 'qualification' => 'NCV: Engineering & Related Design'],
                ['name' => 'Ekurhuleni East TVET College', 'qualification' => 'NCV: Electrical Infrastructure Construction'],
                ['name' => 'Central Johannesburg TVET College', 'qualification' => 'N-Courses: Plumbing'],
            ],
        ],
        'admin' => [
            'label'    => 'Office Administration & Support',
            'subjects' => ['Business Studies', 'Accounting', 'Computer Applications Technology', 'Languages'],
            'institutions' => [
                ['name' => 'Rosebank College', 'qualification' => 'Higher Certificate: Office Administration'],
                ['name' => 'Boston City Campus', 'qualification' => 'Diploma: Business Administration'],
                ['name' => 'Damelin', 'qualification' => 'Certificate: Public Administration'],
            ],
        ],
    ];
}

// Metadata for the 3 questionnaires that make up Subject Choice, in
// the order they should be completed.
function sc_questionnaire_types(): array {
    return [
        'interests' => [
            'label' => 'Interests Questionnaire',
            'desc'  => 'What kind of subjects and activities you\'re drawn to.',
        ],
        'abilities' => [
            'label' => 'Career Abilities Questionnaire',
            'desc'  => 'What you tend to find easy or pick up naturally.',
        ],
        'skills' => [
            'label' => 'Employability Skills Questionnaire',
            'desc'  => 'Skills you\'ve already built through school, home or life.',
        ],
    ];
}

function sc_is_valid_type(string $type): bool {
    return array_key_exists($type, sc_questionnaire_types());
}

// 12 first-person Likert statements per questionnaire type — 2 per
// study field, interleaved so consecutive statements aren't from the
// same field. Each entry: ['category' => ..., 'text' => ...].
function sc_statements(string $type): array {
    $byCategory = [
        'interests' => [
            'stem'      => ['I enjoy figuring out how machines or systems work.', 'I like experimenting to understand why something happens.'],
            'creative'  => ['I enjoy drawing, designing or creating things that look good.', 'I like coming up with original ideas, stories or images.'],
            'social'    => ['I enjoy learning about people, cultures or how society works.', 'I like listening to and supporting people who need help.'],
            'business'  => ['I enjoy thinking about how businesses make and manage money.', 'I like planning, pricing or negotiating deals.'],
            'technical' => ['I enjoy building, repairing or assembling things with my hands.', 'I like working with tools, machinery or equipment.'],
            'admin'     => ['I enjoy keeping records, files and schedules neatly organised.', 'I like following clear step-by-step procedures.'],
        ],
        'abilities' => [
            'stem'      => ['I find it easy to solve maths or logical problems.', 'I pick up new technical or scientific ideas quickly.'],
            'creative'  => ['I can easily come up with creative or original ideas.', 'I have a good eye for colour, shape or design.'],
            'social'    => ['I find it easy to understand how other people are feeling.', 'I explain things clearly so others understand me.'],
            'business'  => ['I am good at spotting ways to save or make money.', 'I can persuade or negotiate with people easily.'],
            'technical' => ['I am good at working out how to fix something that is broken.', 'I have steady, careful hands for practical tasks.'],
            'admin'     => ['I am accurate and careful when working with details.', 'I can manage my own time and tasks without reminders.'],
        ],
        'skills' => [
            'stem'      => ['I have used tools, data or software to solve a technical problem.', 'I have completed a science, tech or maths project successfully.'],
            'creative'  => ['I have produced a piece of art, design or writing I am proud of.', 'I have used creative or design software or tools before.'],
            'social'    => ['I have helped resolve a disagreement between people.', 'I have worked well as part of a team on a shared task.'],
            'business'  => ['I have managed money, a budget or a small project before.', 'I have sold, marketed or promoted something before.'],
            'technical' => ['I have repaired, built or assembled something practical before.', 'I have safely operated tools or equipment before.'],
            'admin'     => ['I have kept organised records or files for something.', 'I have met deadlines by managing my own schedule.'],
        ],
    ];

    $categories = array_keys(sc_categories());
    $set = $byCategory[$type] ?? [];
    $statements = [];
    for ($round = 0; $round < 2; $round++) {
        foreach ($categories as $cat) {
            $statements[] = ['category' => $cat, 'text' => $set[$cat][$round]];
        }
    }
    return $statements;
}

/**
 * @param string $type
 * @param array $answers Flat array indexed 0..11 of ratings 1-5
 * @return array Ranked list of ['key','label','score','max','percent']
 */
function sc_score_questionnaire(string $type, array $answers): array {
    $statements = sc_statements($type);
    $categories = sc_categories();
    $scores = array_fill_keys(array_keys($categories), 0);
    $counts = array_fill_keys(array_keys($categories), 0);

    foreach ($statements as $i => $s) {
        $rating = (int)($answers[$i] ?? 0);
        $scores[$s['category']] += $rating;
        $counts[$s['category']]++;
    }

    $ranked = [];
    foreach ($scores as $cat => $score) {
        $max = $counts[$cat] * 5;
        $ranked[] = [
            'key'     => $cat,
            'label'   => $categories[$cat]['label'],
            'score'   => $score,
            'max'     => $max,
            'percent' => $max > 0 ? (int)round($score / $max * 100) : 0,
        ];
    }

    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
    return $ranked;
}

/**
 * @param array $allAnswers ['interests' => [0..11 => rating], 'abilities' => [...], 'skills' => [...]]
 * @return array Ranked top-3 list of ['key','label','score','max','percent','subjects','institutions']
 */
function sc_combined_report(array $allAnswers): array {
    $categories = sc_categories();
    $scores = array_fill_keys(array_keys($categories), 0);
    $counts = array_fill_keys(array_keys($categories), 0);

    foreach (array_keys(sc_questionnaire_types()) as $type) {
        $answers = $allAnswers[$type] ?? [];
        foreach (sc_statements($type) as $i => $s) {
            $rating = (int)($answers[$i] ?? 0);
            $scores[$s['category']] += $rating;
            $counts[$s['category']]++;
        }
    }

    $ranked = [];
    foreach ($scores as $cat => $score) {
        $max = $counts[$cat] * 5;
        $ranked[] = [
            'key'          => $cat,
            'label'        => $categories[$cat]['label'],
            'score'        => $score,
            'max'          => $max,
            'percent'      => $max > 0 ? (int)round($score / $max * 100) : 0,
            'subjects'     => $categories[$cat]['subjects'],
            'institutions' => $categories[$cat]['institutions'],
        ];
    }

    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($ranked, 0, 3);
}
