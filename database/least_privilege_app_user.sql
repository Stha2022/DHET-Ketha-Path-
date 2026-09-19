-- Run this only AFTER database/khetha_path.sql, as a MySQL admin account.
-- Replace the password with a long random secret before production.
CREATE USER IF NOT EXISTS 'khetha_app'@'%' IDENTIFIED BY 'CHANGE-ME-to-a-long-random-password';
GRANT SELECT ON khetha_path.careers TO 'khetha_app'@'%';
GRANT SELECT ON khetha_path.qualifications TO 'khetha_app'@'%';
GRANT SELECT ON khetha_path.providers TO 'khetha_app'@'%';
GRANT SELECT ON khetha_path.content_sources TO 'khetha_app'@'%';
GRANT SELECT,INSERT,UPDATE ON khetha_path.users TO 'khetha_app'@'%';
GRANT SELECT,INSERT,UPDATE ON khetha_path.learner_profiles TO 'khetha_app'@'%';
GRANT SELECT,INSERT ON khetha_path.assessmentResults TO 'khetha_app'@'%';
GRANT SELECT,INSERT,DELETE ON khetha_path.favourites TO 'khetha_app'@'%';
GRANT SELECT,INSERT,UPDATE ON khetha_path.notifications TO 'khetha_app'@'%';
GRANT SELECT,INSERT,UPDATE ON khetha_path.notification_preferences TO 'khetha_app'@'%';
GRANT SELECT,INSERT,UPDATE ON khetha_path.advisor_requests TO 'khetha_app'@'%';
GRANT SELECT,INSERT ON khetha_path.learner_content_interactions TO 'khetha_app'@'%';
GRANT SELECT,INSERT,UPDATE,DELETE ON khetha_path.learner_cv_edits TO 'khetha_app'@'%';
