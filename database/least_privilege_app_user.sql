-- Khetha Path — a least-privilege database user for the web app.  NOT run automatically.
--
-- Why: the account currently in database/.env has SUPER, DROP, CREATE USER and GRANT OPTION on
-- the whole TiDB cluster. A bug or leaked .env in a CV/profile feature could then read or destroy
-- everything. The app only needs to read reference data and read/write learner rows.
--
-- Run as an admin (once), choose a strong password, then put the new username/password in
-- database/.env (and in Render's environment variables) and retire the old account.

CREATE USER IF NOT EXISTS 'khetha_app'@'%' IDENTIFIED BY 'CHANGE-ME-to-a-long-random-password';

-- Reference data: read only.
GRANT SELECT ON khetha_path.careers                TO 'khetha_app'@'%';
GRANT SELECT ON khetha_path.careerQualifications   TO 'khetha_app'@'%';
GRANT SELECT ON khetha_path.qualifications         TO 'khetha_app'@'%';
GRANT SELECT ON khetha_path.qualificationProviders TO 'khetha_app'@'%';
GRANT SELECT ON khetha_path.providers              TO 'khetha_app'@'%';

-- Learner data: read and write, but never DELETE/DROP/ALTER.
GRANT SELECT, INSERT, UPDATE ON khetha_path.users             TO 'khetha_app'@'%';
GRANT SELECT, INSERT         ON khetha_path.assessmentResults TO 'khetha_app'@'%';  -- append-only history
GRANT SELECT, INSERT, DELETE ON khetha_path.favourites        TO 'khetha_app'@'%';
GRANT SELECT, INSERT, UPDATE ON khetha_path.notifications     TO 'khetha_app'@'%';

-- CV edits: "Reset to what Khetha knows" deletes the learner's row.
GRANT SELECT, INSERT, UPDATE, DELETE ON khetha_path.learner_cv_edits TO 'khetha_app'@'%';

-- Not granted on purpose: users.passwordHash / tempToken are readable by the login code, but the
-- CV code never selects them (see includes/cv.php: explicit column lists, no SELECT *).
