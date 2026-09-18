-- Khetha Path — assessment_results: the one write-contract table for
-- Subject Chooser, Career Choice and Job Fit (subject.php, career-quiz.php,
-- occupation.php). Each completed assessment inserts exactly one new row —
-- never an UPDATE — so history is kept and My Path always reads the
-- latest row per source. This table exists before the dashboard/My Path
-- work that reads it and before subject.php/career-quiz.php/occupation.php
-- are wired to write to it (a later step) — a fresh table with zero rows
-- is the correct, honest "nothing done yet" state for every user until
-- then.
--
-- Distinct from the existing generic `assessments` table in
-- khetha_path.sql (subjects/interests/result_summary, one implied row per
-- user) — that table is not extended or reused, since it can't hold
-- history or the derived/payload split this contract needs. Whether to
-- retire it is a separate decision, out of scope here.
--
-- Run after khetha_path.sql (needs users.id) and
-- migration_reference_tables.sql. Idempotent (CREATE TABLE IF NOT EXISTS).

USE khetha_path;

CREATE TABLE IF NOT EXISTS assessment_results (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source ENUM('subject_chooser', 'career_choice', 'job_fit', 'occupation_view') NOT NULL,
    version VARCHAR(20) NOT NULL DEFAULT '1',   -- scoring logic can change later without corrupting old rows; readers check this before trusting `derived`
    payload JSON NOT NULL,                      -- raw inputs the learner gave (e.g. job_fit: value_rank/context/aptitude/constraints answers)
    derived JSON NULL,                          -- computed output (e.g. job_fit: overall score, per-sub-scale breakdown, flags). NULL for a source that only logs an event (occupation_view) with nothing to compute.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assessment_results_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assessment_results_user_source_id (user_id, source, id)  -- id, not created_at: same-second inserts still order correctly for "latest per source"
);

-- Reference query for "latest row per source for a user" (My Path's read
-- side implements this, not this migration):
--
-- SELECT ar.*
-- FROM assessment_results ar
-- INNER JOIN (
--     SELECT source, MAX(id) AS max_id
--     FROM assessment_results
--     WHERE user_id = ?
--     GROUP BY source
-- ) latest ON ar.id = latest.max_id
-- WHERE ar.user_id = ?;
