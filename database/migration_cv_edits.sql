-- Khetha Path — CV builder support.  NOT run automatically: apply once, by hand, to the
-- khetha_path database (select it first, e.g. `USE khetha_path;`).
--
-- The live schema (camelCase: users, assessmentResults, careers, ...) has no home for a
-- learner's interests, and no place for the edits a learner makes to their CV. This adds both.

-- 1. Interests: the chip labels picked at registration / on My Profile, as a JSON array,
--    e.g. ["Technology","Solving puzzles","Building and fixing"].
ALTER TABLE users
    ADD COLUMN interests JSON NULL AFTER grade;

-- 2. The learner's own CV edits. One row per learner. `edits` is a SPARSE JSON object holding
--    only the fields they changed; everything else keeps following what Khetha knows.
--    "Reset to what Khetha knows" simply deletes the row.
--    Allowed keys (see includes/cv.php): summary, contact_name, contact_email, contact_phone,
--    school_name, school_grade, school_notes, subjects[], results[], interests[],
--    pathway_extra[], achievements[].
CREATE TABLE IF NOT EXISTS learner_cv_edits (
    userID    INT UNSIGNED NOT NULL PRIMARY KEY,
    edits     JSON NOT NULL,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_learner_cv_edits_user FOREIGN KEY (userID) REFERENCES users (userID) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------------------------------
-- What the CV reads from assessmentResults (the contract the app should write to when it is
-- wired to the database). `source` is the enum; the JSON keys below are what cv_build() expects.
-- The newest row per source wins (job_fit: newest row per occupation).
--
--   source = 'subject_chooser'
--     payload: { "grade": "Grade 10", "home_language": "isiXhosa", "fal": "English",
--                "maths_track": "Mathematics", "subjects": ["Physical Sciences", ...],
--                "marks": {"Physical Sciences": 65}, "intended_careers": ["computer_science"] }
--     derived: NULL or anything (not read)
--
--   source = 'career_choice'
--     payload: NULL or the raw answers (not read)
--     derived: { "code": "IRC", "scores": {"R":60,"I":100,...}, "top_careers": ["computer_science", ...] }
--
--   source = 'job_fit'          (one row per occupation checked)
--     payload: NULL or the raw answers (not read)
--     derived: { "occupation_id": "law", "overall": 78, "flags": ["..."] }
--
-- Occupation ids are the slugs used by occupation-data.php ('computer_science'). The `careers`
-- table has integer ids and no slug, so reference data (titles, qualifications, providers)
-- still comes from occupation-data.php until `careers` gets a slug column.
-- ---------------------------------------------------------------------------------------------
