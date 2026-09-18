<?php
/**
 * Khetha Path — canonical occupation directory.
 *
 * Single source of truth for the three entities every quiz/tool queries
 * against: Occupation, Qualification, Provider. Subject Chooser
 * (subject-data.php), Career Choice (career-quiz-data.php) and Job Fit
 * (job-fit-data.php, occupation.php) all read from kp_occupations() /
 * kp_qualifications() / kp_providers() below rather than keeping their
 * own copies — add or correct an occupation here and every tool that
 * uses it updates together.
 *
 * PROTOTYPE DATA. Every record below (RIASEC vectors, work values, work
 * context, aptitudes, subject requirements, APS, demand, salary bands,
 * qualifications and providers) is a plausible placeholder standing in
 * for real NCAP/DHET/SAQA/O*NET-equivalent data. Nothing here should be
 * shown to a learner as authoritative — replace field-by-field as real
 * sourced data becomes available; the shape or functions in this file
 * don't need to change to do that.
 *
 * ---------------------------------------------------------------------
 * Occupation
 *   id, ofo_code      OFO = Organising Framework for Occupations, the
 *                     DHET/DEL standard. Direct NCAP alignment once
 *                     populated with real codes; null until then.
 *   title, alt_titles[]   alt_titles[] drives search, e.g. "doctor" ->
 *                     Medical Practitioner (see kp_search_occupations()).
 *   description, video_url
 *   riasec{R,I,A,S,E,C}   0-100 each. Consumed by Career Choice.
 *   work_values{}     6 values (kp_work_value_keys()), 0-100. Consumed
 *                     by Job Fit.
 *   work_context{}    outdoors, physical, shifts, travel, people, data,
 *                     things, risk (kp_work_context_keys()), 0-100.
 *                     Consumed by Job Fit.
 *   aptitudes{}       8 aptitudes (kp_aptitude_keys()), 0-100. Consumed
 *                     by Job Fit.
 *   subject_requirements[]   {subject, min_percent, necessity:
 *                     required|recommended}. Consumed by Subject Chooser.
 *   math_track        mathematics | math_literacy | either | technical_maths
 *   min_aps
 *   tags[]            green, high_demand, trade — mirrors NCAP's own filters.
 *   qualification_ids[]
 *   demand, salary_band
 *
 *   Plus two fields subject.php's UI already depends on, kept alongside
 *   the schema above rather than bolted on separately:
 *   field             Broad grouping used to bucket occupations in the UI.
 *   pathways[]         Alternate-route blurbs shown on a result card.
 *
 * Qualification
 *   id, saqa_id, title, nqf_level, field,
 *   entry_requirements{aps, subject_minimums[]}, duration, provider_ids[]
 *
 * Provider
 *   id, name, type (university | tvet | cet | private | seta),
 *   campuses[{province, town, lat, lng}], qualification_ids[], contact,
 *   nsfas_accredited
 * ---------------------------------------------------------------------
 */

function kp_work_value_keys(): array {
    return [
        'achievement'        => 'Achievement',
        'independence'       => 'Independence',
        'recognition'        => 'Recognition',
        'relationships'      => 'Relationships',
        'support'            => 'Support',
        'working_conditions' => 'Working Conditions',
    ];
}

// Job Fit asks about these 4 (of the fuller set some occupation records
// still carry in their own work_context JSON) — kept as the clearest,
// least-overlapping spread for a short questionnaire.
function kp_work_context_keys(): array {
    return [
        'physical' => 'Physical demand',
        'shifts'   => 'Shift / irregular hours',
        'travel'   => 'Travel required',
        'people'   => 'Works with people',
    ];
}

// Job Fit asks about these 4 — the general categories; the more
// specialised perceptual/motor subdivisions were dropped to keep the
// questionnaire short.
function kp_aptitude_keys(): array {
    return [
        'verbal'             => 'Verbal',
        'numerical'          => 'Numerical',
        'spatial'            => 'Spatial',
        'manual_dexterity'   => 'Manual Dexterity',
    ];
}

/**
 * @return array<string, array> Keyed by occupation id.
 */
function kp_occupations(): array {
    return [
        'chemical_engineering' => [
            'id' => 'chemical_engineering', 'ofo_code' => null,
            'title' => 'Chemical Engineering', 'alt_titles' => ['Process Engineer', 'Chemical Engineer'],
            'field' => 'Engineering',
            'description' => 'Designs and operates the processes that turn raw materials into everyday products — fuel, plastics, medicine, food.',
            'video_url' => null,
            'riasec' => ['R' => 90, 'I' => 100, 'A' => 10, 'S' => 10, 'E' => 30, 'C' => 40],
            'work_values' => ['achievement' => 85, 'independence' => 60, 'recognition' => 55, 'relationships' => 40, 'support' => 50, 'working_conditions' => 45],
            'work_context' => ['outdoors' => 30, 'physical' => 35, 'shifts' => 40, 'travel' => 30, 'people' => 30, 'data' => 80, 'things' => 75, 'risk' => 55],
            'aptitudes' => ['verbal' => 55, 'numerical' => 90, 'spatial' => 75, 'form_perception' => 65, 'clerical_perception' => 55, 'motor_coordination' => 45, 'finger_dexterity' => 40, 'manual_dexterity' => 40],
            'math_track' => 'mathematics', 'min_aps' => 34,
            'tags' => ['high_demand'],
            'qualification_ids' => ['beng_chem'],
            'demand' => 'high', 'salary_band' => 'R300k – R700k/yr',
            'subject_requirements' => [
                ['subject' => 'Physical Sciences', 'min_percent' => 60, 'necessity' => 'required'],
                ['subject' => 'Mathematics', 'min_percent' => 60, 'necessity' => 'required'],
                ['subject' => 'Life Sciences', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Information Technology', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['TVET N-courses in Chemical/Process Engineering, then apprenticeship', 'University bridging/foundation year to strengthen Maths and Physical Sciences', 'Higher Certificate in Engineering Studies, articulating into a Diploma'],
        ],
        'medicine' => [
            'id' => 'medicine', 'ofo_code' => null,
            'title' => 'Medicine (MBChB)', 'alt_titles' => ['Doctor', 'Medical Practitioner', 'Physician'],
            'field' => 'Health Sciences',
            'description' => 'Diagnoses and treats illness and injury as a registered medical doctor, in hospitals, clinics or private practice.',
            'video_url' => null,
            'riasec' => ['R' => 20, 'I' => 100, 'A' => 10, 'S' => 80, 'E' => 30, 'C' => 20],
            'work_values' => ['achievement' => 90, 'independence' => 55, 'recognition' => 70, 'relationships' => 80, 'support' => 60, 'working_conditions' => 30],
            'work_context' => ['outdoors' => 5, 'physical' => 40, 'shifts' => 90, 'travel' => 15, 'people' => 95, 'data' => 60, 'things' => 45, 'risk' => 50],
            'aptitudes' => ['verbal' => 85, 'numerical' => 70, 'spatial' => 55, 'form_perception' => 60, 'clerical_perception' => 55, 'motor_coordination' => 65, 'finger_dexterity' => 60, 'manual_dexterity' => 55],
            'math_track' => 'mathematics', 'min_aps' => 36,
            'tags' => ['high_demand'],
            'qualification_ids' => ['mbchb'],
            'demand' => 'high', 'salary_band' => 'R400k – R1.2m/yr',
            'subject_requirements' => [
                ['subject' => 'Life Sciences', 'min_percent' => 70, 'necessity' => 'required'],
                ['subject' => 'Physical Sciences', 'min_percent' => 65, 'necessity' => 'required'],
                ['subject' => 'Mathematics', 'min_percent' => 60, 'necessity' => 'required'],
            ],
            'pathways' => ['Higher Certificate/Diploma in a health science (e.g. Nursing, Clinical Technology) with articulation routes', 'University bridging/foundation year in Health Sciences', 'Related degree (e.g. BSc Biomedical Science) with postgraduate entry options'],
        ],
        'actuarial_science' => [
            'id' => 'actuarial_science', 'ofo_code' => null,
            'title' => 'Actuarial Science', 'alt_titles' => ['Actuary'],
            'field' => 'Business & Finance',
            'description' => 'Uses statistics and financial theory to measure and manage risk for insurers, pension funds and financial institutions.',
            'video_url' => null,
            'riasec' => ['R' => 10, 'I' => 90, 'A' => 10, 'S' => 10, 'E' => 50, 'C' => 90],
            'work_values' => ['achievement' => 85, 'independence' => 55, 'recognition' => 60, 'relationships' => 35, 'support' => 40, 'working_conditions' => 65],
            'work_context' => ['outdoors' => 0, 'physical' => 5, 'shifts' => 5, 'travel' => 10, 'people' => 30, 'data' => 95, 'things' => 10, 'risk' => 5],
            'aptitudes' => ['verbal' => 60, 'numerical' => 95, 'spatial' => 45, 'form_perception' => 50, 'clerical_perception' => 70, 'motor_coordination' => 25, 'finger_dexterity' => 20, 'manual_dexterity' => 15],
            'math_track' => 'mathematics', 'min_aps' => 38,
            'tags' => ['high_demand'],
            'qualification_ids' => ['bsc_actuarial'],
            'demand' => 'high', 'salary_band' => 'R350k – R900k/yr',
            'subject_requirements' => [
                ['subject' => 'Mathematics', 'min_percent' => 80, 'necessity' => 'required'],
                ['subject' => 'Accounting', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Physical Sciences', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['BCom degree with a strong Maths/Statistics major, then bridge into actuarial exams', 'Higher Certificate in Financial/Statistical studies, articulating into a BCom'],
        ],
        'computer_science' => [
            'id' => 'computer_science', 'ofo_code' => null,
            'title' => 'Computer Science / Software Development', 'alt_titles' => ['Software Developer', 'Programmer', 'Software Engineer'],
            'field' => 'Information Technology',
            'description' => 'Designs, builds and maintains the software behind websites, apps and the systems businesses run on.',
            'video_url' => null,
            'riasec' => ['R' => 50, 'I' => 100, 'A' => 20, 'S' => 10, 'E' => 30, 'C' => 50],
            'work_values' => ['achievement' => 80, 'independence' => 75, 'recognition' => 45, 'relationships' => 35, 'support' => 40, 'working_conditions' => 70],
            'work_context' => ['outdoors' => 0, 'physical' => 5, 'shifts' => 15, 'travel' => 5, 'people' => 25, 'data' => 95, 'things' => 40, 'risk' => 5],
            'aptitudes' => ['verbal' => 55, 'numerical' => 80, 'spatial' => 65, 'form_perception' => 60, 'clerical_perception' => 55, 'motor_coordination' => 35, 'finger_dexterity' => 45, 'manual_dexterity' => 20],
            'math_track' => 'mathematics', 'min_aps' => 30,
            'tags' => ['high_demand'],
            'qualification_ids' => ['bsc_comp_sci'],
            'demand' => 'high', 'salary_band' => 'R250k – R800k/yr',
            'subject_requirements' => [
                ['subject' => 'Mathematics', 'min_percent' => 60, 'necessity' => 'required'],
                ['subject' => 'Information Technology', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Physical Sciences', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['TVET National Certificate (Vocational) in IT, then Higher Certificate → Diploma articulation', 'Private/college Higher Certificate in IT with degree articulation'],
        ],
        'civil_engineering' => [
            'id' => 'civil_engineering', 'ofo_code' => null,
            'title' => 'Civil Engineering', 'alt_titles' => ['Civil Engineer'],
            'field' => 'Engineering',
            'description' => 'Plans, designs and oversees construction of roads, bridges, water systems and buildings.',
            'video_url' => null,
            'riasec' => ['R' => 100, 'I' => 80, 'A' => 10, 'S' => 10, 'E' => 30, 'C' => 50],
            'work_values' => ['achievement' => 80, 'independence' => 55, 'recognition' => 55, 'relationships' => 45, 'support' => 45, 'working_conditions' => 35],
            'work_context' => ['outdoors' => 70, 'physical' => 55, 'shifts' => 30, 'travel' => 50, 'people' => 45, 'data' => 65, 'things' => 80, 'risk' => 60],
            'aptitudes' => ['verbal' => 50, 'numerical' => 85, 'spatial' => 80, 'form_perception' => 70, 'clerical_perception' => 50, 'motor_coordination' => 45, 'finger_dexterity' => 35, 'manual_dexterity' => 40],
            'math_track' => 'mathematics', 'min_aps' => 32,
            'tags' => ['high_demand'],
            'qualification_ids' => ['beng_civil'],
            'demand' => 'high', 'salary_band' => 'R280k – R650k/yr',
            'subject_requirements' => [
                ['subject' => 'Physical Sciences', 'min_percent' => 55, 'necessity' => 'required'],
                ['subject' => 'Mathematics', 'min_percent' => 60, 'necessity' => 'required'],
                ['subject' => 'Engineering Graphics & Design', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['TVET N-courses in Civil Engineering, then apprenticeship toward technologist status', 'University bridging/foundation year to strengthen Maths and Physical Sciences'],
        ],
        'chartered_accountancy' => [
            'id' => 'chartered_accountancy', 'ofo_code' => null,
            'title' => 'Chartered Accountancy (CA(SA))', 'alt_titles' => ['Chartered Accountant', 'Accountant'],
            'field' => 'Business & Finance',
            'description' => 'Audits, reports on and advises on the finances of businesses and organisations, registered with SAICA.',
            'video_url' => null,
            'riasec' => ['R' => 10, 'I' => 30, 'A' => 10, 'S' => 20, 'E' => 50, 'C' => 100],
            'work_values' => ['achievement' => 80, 'independence' => 50, 'recognition' => 55, 'relationships' => 40, 'support' => 45, 'working_conditions' => 55],
            'work_context' => ['outdoors' => 0, 'physical' => 5, 'shifts' => 20, 'travel' => 15, 'people' => 45, 'data' => 95, 'things' => 5, 'risk' => 5],
            'aptitudes' => ['verbal' => 60, 'numerical' => 90, 'spatial' => 30, 'form_perception' => 45, 'clerical_perception' => 80, 'motor_coordination' => 20, 'finger_dexterity' => 20, 'manual_dexterity' => 15],
            'math_track' => 'mathematics', 'min_aps' => 32,
            'tags' => ['high_demand'],
            'qualification_ids' => ['ca_sa_stream'],
            'demand' => 'high', 'salary_band' => 'R250k – R700k/yr',
            'subject_requirements' => [
                ['subject' => 'Mathematics', 'min_percent' => 60, 'necessity' => 'required'],
                ['subject' => 'Accounting', 'min_percent' => 60, 'necessity' => 'required'],
                ['subject' => 'Business Studies', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Economics', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['Higher Certificate in Accounting, articulating into a BCom Accounting Diploma/Degree', 'National Diploma in Financial Accounting via a university of technology'],
        ],
        'business_management' => [
            'id' => 'business_management', 'ofo_code' => null,
            'title' => 'Business Management', 'alt_titles' => ['Business Manager', 'Operations Manager'],
            'field' => 'Business & Finance',
            'description' => 'Plans and runs the day-to-day operations of a business or a team within one.',
            'video_url' => null,
            'riasec' => ['R' => 10, 'I' => 20, 'A' => 10, 'S' => 40, 'E' => 100, 'C' => 60],
            'work_values' => ['achievement' => 75, 'independence' => 60, 'recognition' => 65, 'relationships' => 55, 'support' => 40, 'working_conditions' => 50],
            'work_context' => ['outdoors' => 5, 'physical' => 10, 'shifts' => 25, 'travel' => 35, 'people' => 80, 'data' => 65, 'things' => 15, 'risk' => 10],
            'aptitudes' => ['verbal' => 70, 'numerical' => 60, 'spatial' => 30, 'form_perception' => 35, 'clerical_perception' => 55, 'motor_coordination' => 25, 'finger_dexterity' => 20, 'manual_dexterity' => 15],
            'math_track' => 'either', 'min_aps' => 24,
            'tags' => [],
            'qualification_ids' => ['bcom_business_management'],
            'demand' => 'medium', 'salary_band' => 'R180k – R500k/yr',
            'subject_requirements' => [
                ['subject' => 'Business Studies', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Accounting', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Economics', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Mathematics', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['Higher Certificate in Business/Office Administration, articulating into a Diploma', 'TVET N-courses in Business Management'],
        ],
        'law' => [
            'id' => 'law', 'ofo_code' => null,
            'title' => 'Law (LLB)', 'alt_titles' => ['Lawyer', 'Attorney', 'Advocate'],
            'field' => 'Humanities & Law',
            'description' => 'Advises on and argues legal matters as an attorney or advocate, after admission to the profession.',
            'video_url' => null,
            'riasec' => ['R' => 10, 'I' => 30, 'A' => 30, 'S' => 60, 'E' => 100, 'C' => 30],
            'work_values' => ['achievement' => 85, 'independence' => 65, 'recognition' => 70, 'relationships' => 55, 'support' => 40, 'working_conditions' => 35],
            'work_context' => ['outdoors' => 0, 'physical' => 5, 'shifts' => 20, 'travel' => 20, 'people' => 80, 'data' => 75, 'things' => 5, 'risk' => 10],
            'aptitudes' => ['verbal' => 90, 'numerical' => 45, 'spatial' => 25, 'form_perception' => 35, 'clerical_perception' => 55, 'motor_coordination' => 15, 'finger_dexterity' => 15, 'manual_dexterity' => 10],
            'math_track' => 'either', 'min_aps' => 26,
            'tags' => [],
            'qualification_ids' => ['llb'],
            'demand' => 'medium', 'salary_band' => 'R200k – R650k/yr',
            'subject_requirements' => [
                ['subject' => 'History', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Business Studies', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['Higher Certificate in Legal Studies/Paralegal Studies, articulating into an LLB', 'Diploma in Paralegal Studies with progression routes'],
        ],
        'foundation_phase_teaching' => [
            'id' => 'foundation_phase_teaching', 'ofo_code' => null,
            'title' => 'Foundation Phase Teaching', 'alt_titles' => ['Teacher', 'Educator'],
            'field' => 'Education',
            'description' => 'Teaches Grade R-3 learners the foundational literacy, numeracy and life skills the rest of school builds on.',
            'video_url' => null,
            'riasec' => ['R' => 10, 'I' => 20, 'A' => 50, 'S' => 100, 'E' => 40, 'C' => 30],
            'work_values' => ['achievement' => 65, 'independence' => 40, 'recognition' => 45, 'relationships' => 90, 'support' => 70, 'working_conditions' => 50],
            'work_context' => ['outdoors' => 10, 'physical' => 25, 'shifts' => 10, 'travel' => 5, 'people' => 95, 'data' => 30, 'things' => 25, 'risk' => 10],
            'aptitudes' => ['verbal' => 80, 'numerical' => 45, 'spatial' => 30, 'form_perception' => 35, 'clerical_perception' => 45, 'motor_coordination' => 40, 'finger_dexterity' => 35, 'manual_dexterity' => 30],
            'math_track' => 'either', 'min_aps' => 24,
            'tags' => ['high_demand'],
            'qualification_ids' => ['bed_foundation'],
            'demand' => 'high', 'salary_band' => 'R180k – R380k/yr',
            'subject_requirements' => [
                ['subject' => 'Life Sciences', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['Higher Certificate in Education, articulating into a B.Ed', 'Diploma in Early Childhood Development with B.Ed articulation'],
        ],
        'architecture' => [
            'id' => 'architecture', 'ofo_code' => null,
            'title' => 'Architecture', 'alt_titles' => ['Architect'],
            'field' => 'Built Environment',
            'description' => 'Designs buildings and spaces, balancing function, safety and aesthetics from concept through construction.',
            'video_url' => null,
            'riasec' => ['R' => 60, 'I' => 60, 'A' => 100, 'S' => 10, 'E' => 30, 'C' => 30],
            'work_values' => ['achievement' => 80, 'independence' => 70, 'recognition' => 60, 'relationships' => 40, 'support' => 40, 'working_conditions' => 45],
            'work_context' => ['outdoors' => 30, 'physical' => 25, 'shifts' => 20, 'travel' => 30, 'people' => 45, 'data' => 55, 'things' => 65, 'risk' => 20],
            'aptitudes' => ['verbal' => 55, 'numerical' => 65, 'spatial' => 90, 'form_perception' => 80, 'clerical_perception' => 40, 'motor_coordination' => 45, 'finger_dexterity' => 45, 'manual_dexterity' => 35],
            'math_track' => 'mathematics', 'min_aps' => 32,
            'tags' => [],
            'qualification_ids' => ['barch'],
            'demand' => 'medium', 'salary_band' => 'R220k – R600k/yr',
            'subject_requirements' => [
                ['subject' => 'Mathematics', 'min_percent' => 60, 'necessity' => 'required'],
                ['subject' => 'Engineering Graphics & Design', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Visual Arts', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Physical Sciences', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['National Diploma in Architectural Technology via a university of technology', 'Higher Certificate in Built Environment studies, articulating into a Diploma'],
        ],
        'graphic_design' => [
            'id' => 'graphic_design', 'ofo_code' => null,
            'title' => 'Graphic Design', 'alt_titles' => ['Graphic Designer', 'Visual Designer'],
            'field' => 'Creative Arts & Design',
            'description' => 'Creates visual content — branding, layouts, digital and print media — for businesses and campaigns.',
            'video_url' => null,
            'riasec' => ['R' => 10, 'I' => 20, 'A' => 100, 'S' => 10, 'E' => 40, 'C' => 20],
            'work_values' => ['achievement' => 65, 'independence' => 75, 'recognition' => 55, 'relationships' => 35, 'support' => 35, 'working_conditions' => 55],
            'work_context' => ['outdoors' => 0, 'physical' => 5, 'shifts' => 15, 'travel' => 10, 'people' => 35, 'data' => 45, 'things' => 40, 'risk' => 5],
            'aptitudes' => ['verbal' => 45, 'numerical' => 35, 'spatial' => 85, 'form_perception' => 85, 'clerical_perception' => 40, 'motor_coordination' => 45, 'finger_dexterity' => 50, 'manual_dexterity' => 30],
            'math_track' => 'either', 'min_aps' => null,
            'tags' => [],
            'qualification_ids' => ['dip_graphic_design'],
            'demand' => 'medium', 'salary_band' => 'R150k – R400k/yr',
            'subject_requirements' => [
                ['subject' => 'Visual Arts', 'min_percent' => 50, 'necessity' => 'required'],
                ['subject' => 'Information Technology', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Engineering Graphics & Design', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['TVET/private college Higher Certificate in Graphic Design, articulating into a Diploma', 'Short courses + a design portfolio route into a Diploma programme'],
        ],
        'electrician' => [
            'id' => 'electrician', 'ofo_code' => null,
            'title' => 'Electrician (Artisan Trade)', 'alt_titles' => ['Electrician'],
            'field' => 'Technical Trades',
            'description' => 'Installs, maintains and repairs electrical wiring and systems in homes, businesses and industry, as a qualified artisan.',
            'video_url' => null,
            'riasec' => ['R' => 100, 'I' => 50, 'A' => 10, 'S' => 10, 'E' => 20, 'C' => 40],
            'work_values' => ['achievement' => 65, 'independence' => 65, 'recognition' => 40, 'relationships' => 35, 'support' => 40, 'working_conditions' => 30],
            'work_context' => ['outdoors' => 55, 'physical' => 75, 'shifts' => 40, 'travel' => 45, 'people' => 30, 'data' => 20, 'things' => 95, 'risk' => 75],
            'aptitudes' => ['verbal' => 35, 'numerical' => 55, 'spatial' => 65, 'form_perception' => 60, 'clerical_perception' => 40, 'motor_coordination' => 80, 'finger_dexterity' => 75, 'manual_dexterity' => 80],
            'math_track' => 'either', 'min_aps' => null,
            'tags' => ['trade', 'high_demand'],
            'qualification_ids' => ['electrician_trade'],
            'demand' => 'high', 'salary_band' => 'R150k – R350k/yr',
            'subject_requirements' => [
                ['subject' => 'Technical Sciences', 'min_percent' => 50, 'necessity' => 'required'],
                ['subject' => 'Technical Mathematics', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Engineering Graphics & Design', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['TVET N-courses (N1–N6) in Electrical Engineering, then an apprenticeship and trade test', 'Direct workplace apprenticeship with block release to a TVET college'],
        ],
        'agricultural_science' => [
            'id' => 'agricultural_science', 'ofo_code' => null,
            'title' => 'Agricultural Sciences (BSc Agric)', 'alt_titles' => ['Agricultural Scientist', 'Agronomist'],
            'field' => 'Agriculture',
            'description' => 'Applies science to farming and food production — soil, crops, livestock and sustainable land use.',
            'video_url' => null,
            'riasec' => ['R' => 80, 'I' => 90, 'A' => 10, 'S' => 20, 'E' => 30, 'C' => 30],
            'work_values' => ['achievement' => 70, 'independence' => 65, 'recognition' => 45, 'relationships' => 40, 'support' => 45, 'working_conditions' => 30],
            'work_context' => ['outdoors' => 85, 'physical' => 60, 'shifts' => 25, 'travel' => 40, 'people' => 35, 'data' => 55, 'things' => 60, 'risk' => 35],
            'aptitudes' => ['verbal' => 50, 'numerical' => 65, 'spatial' => 45, 'form_perception' => 50, 'clerical_perception' => 45, 'motor_coordination' => 50, 'finger_dexterity' => 40, 'manual_dexterity' => 45],
            'math_track' => 'mathematics', 'min_aps' => 28,
            'tags' => ['green'],
            'qualification_ids' => ['bsc_agric'],
            'demand' => 'medium', 'salary_band' => 'R180k – R450k/yr',
            'subject_requirements' => [
                ['subject' => 'Agricultural Sciences', 'min_percent' => 55, 'necessity' => 'required'],
                ['subject' => 'Mathematics', 'min_percent' => 50, 'necessity' => 'required'],
                ['subject' => 'Life Sciences', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Physical Sciences', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['National Diploma in Agriculture via a university of technology', 'Higher Certificate in Agricultural Management, articulating into a Diploma'],
        ],
        'journalism_media' => [
            'id' => 'journalism_media', 'ofo_code' => null,
            'title' => 'Journalism & Media', 'alt_titles' => ['Journalist', 'Reporter'],
            'field' => 'Humanities & Media',
            'description' => 'Researches, writes and produces news and feature content for print, broadcast or digital media.',
            'video_url' => null,
            'riasec' => ['R' => 10, 'I' => 40, 'A' => 90, 'S' => 50, 'E' => 60, 'C' => 20],
            'work_values' => ['achievement' => 70, 'independence' => 65, 'recognition' => 65, 'relationships' => 55, 'support' => 35, 'working_conditions' => 30],
            'work_context' => ['outdoors' => 35, 'physical' => 15, 'shifts' => 55, 'travel' => 50, 'people' => 75, 'data' => 60, 'things' => 15, 'risk' => 25],
            'aptitudes' => ['verbal' => 90, 'numerical' => 40, 'spatial' => 30, 'form_perception' => 35, 'clerical_perception' => 45, 'motor_coordination' => 25, 'finger_dexterity' => 30, 'manual_dexterity' => 15],
            'math_track' => 'either', 'min_aps' => 24,
            'tags' => [],
            'qualification_ids' => ['dip_journalism'],
            'demand' => 'medium', 'salary_band' => 'R150k – R380k/yr',
            'subject_requirements' => [
                ['subject' => 'History', 'min_percent' => null, 'necessity' => 'recommended'],
                ['subject' => 'Dramatic Arts', 'min_percent' => null, 'necessity' => 'recommended'],
            ],
            'pathways' => ['Higher Certificate in Media/Communication Studies, articulating into a Diploma', 'TVET National Certificate (Vocational) in Media Studies'],
        ],
    ];
}

/**
 * @return array<string, array> Keyed by qualification id.
 */
function kp_qualifications(): array {
    return [
        'beng_chem' => [
            'id' => 'beng_chem', 'saqa_id' => null, 'title' => 'BEng Chemical Engineering', 'nqf_level' => 8,
            'field' => 'Engineering', 'entry_requirements' => ['aps' => 34, 'subject_minimums' => [['subject' => 'Mathematics', 'min_percent' => 60], ['subject' => 'Physical Sciences', 'min_percent' => 60]]],
            'duration' => '4 years', 'provider_ids' => ['uct', 'wits'],
        ],
        'mbchb' => [
            'id' => 'mbchb', 'saqa_id' => null, 'title' => 'Bachelor of Medicine and Surgery (MBChB)', 'nqf_level' => 9,
            'field' => 'Health Sciences', 'entry_requirements' => ['aps' => 36, 'subject_minimums' => [['subject' => 'Life Sciences', 'min_percent' => 70], ['subject' => 'Physical Sciences', 'min_percent' => 65]]],
            'duration' => '6 years', 'provider_ids' => ['uct', 'wits'],
        ],
        'bsc_actuarial' => [
            'id' => 'bsc_actuarial', 'saqa_id' => null, 'title' => 'BSc Actuarial Science', 'nqf_level' => 7,
            'field' => 'Business & Finance', 'entry_requirements' => ['aps' => 38, 'subject_minimums' => [['subject' => 'Mathematics', 'min_percent' => 80]]],
            'duration' => '3 years', 'provider_ids' => ['uct'],
        ],
        'bsc_comp_sci' => [
            'id' => 'bsc_comp_sci', 'saqa_id' => null, 'title' => 'BSc Computer Science', 'nqf_level' => 7,
            'field' => 'Information Technology', 'entry_requirements' => ['aps' => 30, 'subject_minimums' => [['subject' => 'Mathematics', 'min_percent' => 60]]],
            'duration' => '3 years', 'provider_ids' => ['uct', 'wits', 'tut'],
        ],
        'beng_civil' => [
            'id' => 'beng_civil', 'saqa_id' => null, 'title' => 'BEng Civil Engineering', 'nqf_level' => 8,
            'field' => 'Engineering', 'entry_requirements' => ['aps' => 32, 'subject_minimums' => [['subject' => 'Mathematics', 'min_percent' => 60], ['subject' => 'Physical Sciences', 'min_percent' => 55]]],
            'duration' => '4 years', 'provider_ids' => ['wits', 'uct'],
        ],
        'ca_sa_stream' => [
            'id' => 'ca_sa_stream', 'saqa_id' => null, 'title' => 'BCom Accounting (CA(SA) stream)', 'nqf_level' => 7,
            'field' => 'Business & Finance', 'entry_requirements' => ['aps' => 32, 'subject_minimums' => [['subject' => 'Mathematics', 'min_percent' => 60], ['subject' => 'Accounting', 'min_percent' => 60]]],
            'duration' => '3 years', 'provider_ids' => ['uct', 'wits'],
        ],
        'bcom_business_management' => [
            'id' => 'bcom_business_management', 'saqa_id' => null, 'title' => 'BCom Business Management', 'nqf_level' => 7,
            'field' => 'Business & Finance', 'entry_requirements' => ['aps' => 24, 'subject_minimums' => []],
            'duration' => '3 years', 'provider_ids' => ['uj'],
        ],
        'llb' => [
            'id' => 'llb', 'saqa_id' => null, 'title' => 'Bachelor of Laws (LLB)', 'nqf_level' => 8,
            'field' => 'Humanities & Law', 'entry_requirements' => ['aps' => 26, 'subject_minimums' => []],
            'duration' => '4 years', 'provider_ids' => ['wits', 'uct'],
        ],
        'bed_foundation' => [
            'id' => 'bed_foundation', 'saqa_id' => null, 'title' => 'Bachelor of Education (Foundation Phase)', 'nqf_level' => 7,
            'field' => 'Education', 'entry_requirements' => ['aps' => 24, 'subject_minimums' => []],
            'duration' => '4 years', 'provider_ids' => ['uj'],
        ],
        'barch' => [
            'id' => 'barch', 'saqa_id' => null, 'title' => 'Bachelor of Architecture', 'nqf_level' => 9,
            'field' => 'Built Environment', 'entry_requirements' => ['aps' => 32, 'subject_minimums' => [['subject' => 'Mathematics', 'min_percent' => 60]]],
            'duration' => '5 years', 'provider_ids' => ['uct'],
        ],
        'dip_graphic_design' => [
            'id' => 'dip_graphic_design', 'saqa_id' => null, 'title' => 'Diploma in Graphic Design', 'nqf_level' => 6,
            'field' => 'Creative Arts & Design', 'entry_requirements' => ['aps' => null, 'subject_minimums' => [['subject' => 'Visual Arts', 'min_percent' => 50]]],
            'duration' => '3 years', 'provider_ids' => ['tut'],
        ],
        'electrician_trade' => [
            'id' => 'electrician_trade', 'saqa_id' => null, 'title' => 'N1–N6 Electrical Engineering + Trade Test', 'nqf_level' => 5,
            'field' => 'Technical Trades', 'entry_requirements' => ['aps' => null, 'subject_minimums' => [['subject' => 'Technical Sciences', 'min_percent' => 50]]],
            'duration' => '2–4 years incl. apprenticeship', 'provider_ids' => ['central_johannesburg_tvet'],
        ],
        'bsc_agric' => [
            'id' => 'bsc_agric', 'saqa_id' => null, 'title' => 'BSc Agriculture', 'nqf_level' => 7,
            'field' => 'Agriculture', 'entry_requirements' => ['aps' => 28, 'subject_minimums' => [['subject' => 'Mathematics', 'min_percent' => 50], ['subject' => 'Agricultural Sciences', 'min_percent' => 55]]],
            'duration' => '4 years', 'provider_ids' => ['up'],
        ],
        'dip_journalism' => [
            'id' => 'dip_journalism', 'saqa_id' => null, 'title' => 'Diploma in Journalism', 'nqf_level' => 6,
            'field' => 'Humanities & Media', 'entry_requirements' => ['aps' => 24, 'subject_minimums' => []],
            'duration' => '3 years', 'provider_ids' => ['tut'],
        ],
    ];
}

/**
 * @return array<string, array> Keyed by provider id.
 */
function kp_providers(): array {
    return [
        'uct' => [
            'id' => 'uct', 'name' => 'University of Cape Town', 'type' => 'university',
            'campuses' => [['province' => 'Western Cape', 'town' => 'Cape Town', 'lat' => -33.9577, 'lng' => 18.4612]],
            'qualification_ids' => ['beng_chem', 'mbchb', 'bsc_actuarial', 'bsc_comp_sci', 'beng_civil', 'ca_sa_stream', 'llb', 'barch'],
            'contact' => ['website' => 'https://www.uct.ac.za', 'phone' => null, 'email' => null],
            'nsfas_accredited' => true,
        ],
        'wits' => [
            'id' => 'wits', 'name' => 'University of the Witwatersrand', 'type' => 'university',
            'campuses' => [['province' => 'Gauteng', 'town' => 'Johannesburg', 'lat' => -26.1929, 'lng' => 28.0305]],
            'qualification_ids' => ['beng_chem', 'mbchb', 'bsc_comp_sci', 'beng_civil', 'ca_sa_stream', 'llb'],
            'contact' => ['website' => 'https://www.wits.ac.za', 'phone' => null, 'email' => null],
            'nsfas_accredited' => true,
        ],
        'uj' => [
            'id' => 'uj', 'name' => 'University of Johannesburg', 'type' => 'university',
            'campuses' => [['province' => 'Gauteng', 'town' => 'Johannesburg', 'lat' => -26.1826, 'lng' => 27.9974]],
            'qualification_ids' => ['bcom_business_management', 'bed_foundation'],
            'contact' => ['website' => 'https://www.uj.ac.za', 'phone' => null, 'email' => null],
            'nsfas_accredited' => true,
        ],
        'up' => [
            'id' => 'up', 'name' => 'University of Pretoria', 'type' => 'university',
            'campuses' => [['province' => 'Gauteng', 'town' => 'Pretoria', 'lat' => -25.7545, 'lng' => 28.2314]],
            'qualification_ids' => ['bsc_agric'],
            'contact' => ['website' => 'https://www.up.ac.za', 'phone' => null, 'email' => null],
            'nsfas_accredited' => true,
        ],
        'tut' => [
            'id' => 'tut', 'name' => 'Tshwane University of Technology', 'type' => 'university',
            'campuses' => [['province' => 'Gauteng', 'town' => 'Pretoria', 'lat' => -25.7311, 'lng' => 28.1614]],
            'qualification_ids' => ['bsc_comp_sci', 'dip_graphic_design', 'dip_journalism'],
            'contact' => ['website' => 'https://www.tut.ac.za', 'phone' => null, 'email' => null],
            'nsfas_accredited' => true,
        ],
        'central_johannesburg_tvet' => [
            'id' => 'central_johannesburg_tvet', 'name' => 'Central Johannesburg TVET College', 'type' => 'tvet',
            'campuses' => [['province' => 'Gauteng', 'town' => 'Johannesburg', 'lat' => -26.2041, 'lng' => 28.0473]],
            'qualification_ids' => ['electrician_trade'],
            'contact' => ['website' => 'https://www.cjc.edu.za', 'phone' => null, 'email' => null],
            'nsfas_accredited' => true,
        ],
    ];
}

function kp_occupation(string $id): ?array {
    return kp_occupations()[$id] ?? null;
}

function kp_qualification(string $id): ?array {
    return kp_qualifications()[$id] ?? null;
}

function kp_provider(string $id): ?array {
    return kp_providers()[$id] ?? null;
}

/**
 * @return array Qualification records for an occupation's qualification_ids[].
 */
function kp_qualifications_for_occupation(string $occupationId): array {
    $occ = kp_occupation($occupationId);
    if (!$occ) return [];
    $quals = kp_qualifications();
    return array_values(array_filter(array_map(fn($id) => $quals[$id] ?? null, $occ['qualification_ids'])));
}

/**
 * @return array Provider records offering a given qualification.
 */
function kp_providers_for_qualification(string $qualificationId): array {
    $qual = kp_qualification($qualificationId);
    if (!$qual) return [];
    $providers = kp_providers();
    return array_values(array_filter(array_map(fn($id) => $providers[$id] ?? null, $qual['provider_ids'])));
}

/**
 * Free-text search across title and alt_titles, e.g. "doctor" -> Medical
 * Practitioner. Case-insensitive substring match.
 * @return array Matching occupation records.
 */
function kp_search_occupations(string $query): array {
    $query = trim(mb_strtolower($query));
    if ($query === '') return [];
    $matches = [];
    foreach (kp_occupations() as $occ) {
        $haystack = mb_strtolower($occ['title'] . ' ' . implode(' ', $occ['alt_titles']));
        if (str_contains($haystack, $query)) $matches[] = $occ;
    }
    return $matches;
}
