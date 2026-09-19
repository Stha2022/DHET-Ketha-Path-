-- Khetha Path — sample learners so the admin dashboard has something to show.
-- NOT run automatically. Apply by hand to the khetha_path database.
--
-- All sample learners use @seed.khetha.test emails and userIDs 9001-9014, so they are easy
-- to remove:  DELETE FROM users WHERE email LIKE '%@seed.khetha.test';
-- (assessmentResults rows are removed with them only if the foreign key cascades; otherwise run
--  DELETE FROM assessmentResults WHERE userID BETWEEN 9001 AND 9014; first.)
-- The password hash is a placeholder that matches no password, so these accounts cannot sign in.

INSERT INTO users (userID, firstName, lastName, email, grade, passwordHash, consentAt) VALUES
 (9001,'Sample','Learner1','l1@seed.khetha.test','grade 10','!',NOW()),
 (9002,'Sample','Learner2','l2@seed.khetha.test','grade 10','!',NOW()),
 (9003,'Sample','Learner3','l3@seed.khetha.test','grade 10','!',NOW()),
 (9004,'Sample','Learner4','l4@seed.khetha.test','grade 11','!',NOW()),
 (9005,'Sample','Learner5','l5@seed.khetha.test','grade 11','!',NOW()),
 (9006,'Sample','Learner6','l6@seed.khetha.test','grade 11','!',NOW()),
 (9007,'Sample','Learner7','l7@seed.khetha.test','grade 12','!',NOW()),
 (9008,'Sample','Learner8','l8@seed.khetha.test','grade 12','!',NOW()),
 (9009,'Sample','Learner9','l9@seed.khetha.test','grade 9','!',NOW()),
 (9010,'Sample','Learner10','l10@seed.khetha.test','grade 9','!',NOW()),
 (9011,'Sample','Learner11','l11@seed.khetha.test','grade 12','!',NOW()),
 (9012,'Sample','Learner12','l12@seed.khetha.test','out of school','!',NOW());

-- Two learners who signed up weeks ago and never used a tool (early-warning: inactive / no direction).
INSERT INTO users (userID, firstName, lastName, email, grade, passwordHash, consentAt, createdAt) VALUES
 (9013,'Sample','Learner13','l13@seed.khetha.test','grade 12','!',NOW(),NOW() - INTERVAL 30 DAY),
 (9014,'Sample','Learner14','l14@seed.khetha.test','grade 10','!',NOW(),NOW() - INTERVAL 20 DAY);

-- Subject Chooser results: the subjects/marks each learner has and the careers they are aiming for.
INSERT INTO assessmentResults (userID, source, payload, derived) VALUES
 (9001,'subject_chooser','{"grade":"Grade 10","maths_track":"Mathematical Literacy","subjects":["Life Sciences","Geography"],"marks":{},"intended_careers":["computer_science","civil_engineering"]}',NULL),
 (9002,'subject_chooser','{"grade":"Grade 10","maths_track":"Mathematical Literacy","subjects":["Accounting","Business Studies"],"marks":{},"intended_careers":["chartered_accountancy"]}',NULL),
 (9003,'subject_chooser','{"grade":"Grade 10","maths_track":"Mathematics","subjects":["Physical Sciences","Life Sciences"],"marks":{"Mathematics":48,"Physical Sciences":52},"intended_careers":["medicine"]}',NULL),
 (9004,'subject_chooser','{"grade":"Grade 11","maths_track":"Mathematics","subjects":["Physical Sciences","Information Technology"],"marks":{"Mathematics":72,"Physical Sciences":66},"intended_careers":["computer_science","civil_engineering"]}',NULL),
 (9005,'subject_chooser','{"grade":"Grade 11","maths_track":"Mathematical Literacy","subjects":["Geography","History"],"marks":{},"intended_careers":["architecture","computer_science"]}',NULL),
 (9006,'subject_chooser','{"grade":"Grade 11","maths_track":"Mathematics","subjects":["Accounting"],"marks":{"Mathematics":55,"Accounting":70},"intended_careers":["chartered_accountancy"]}',NULL),
 (9007,'subject_chooser','{"grade":"Grade 12","maths_track":"Mathematics","subjects":["Physical Sciences","Life Sciences"],"marks":{"Mathematics":81,"Physical Sciences":74,"Life Sciences":75},"intended_careers":["medicine"]}',NULL),
 (9008,'subject_chooser','{"grade":"Grade 12","maths_track":"Mathematical Literacy","subjects":["Agricultural Sciences"],"marks":{"Agricultural Sciences":60},"intended_careers":["agricultural_science"]}',NULL),
 (9011,'subject_chooser','{"grade":"Grade 12","maths_track":"Mathematics","subjects":["Physical Sciences"],"marks":{"Mathematics":58,"Physical Sciences":50},"intended_careers":["civil_engineering","chemical_engineering"]}',NULL);

-- Career Choice results: the careers the quiz ranked highest for each learner.
INSERT INTO assessmentResults (userID, source, payload, derived) VALUES
 (9001,'career_choice',NULL,'{"code":"IRC","top_careers":["computer_science","electrician","civil_engineering"]}'),
 (9002,'career_choice',NULL,'{"code":"CEI","top_careers":["chartered_accountancy","business_management"]}'),
 (9009,'career_choice',NULL,'{"code":"SAE","top_careers":["foundation_phase_teaching","journalism_media","law"]}'),
 (9010,'career_choice',NULL,'{"code":"AES","top_careers":["graphic_design","journalism_media"]}'),
 (9012,'career_choice',NULL,'{"code":"RIC","top_careers":["electrician"]}');

-- Job Fit results: one row per career checked, with the warning flags it raised.
INSERT INTO assessmentResults (userID, source, payload, derived) VALUES
 (9001,'job_fit',NULL,'{"occupation_id":"computer_science","overall":71,"flags":["This job leans heavily on numerical reasoning — an area you rated yourself lower on."]}'),
 (9002,'job_fit',NULL,'{"occupation_id":"chartered_accountancy","overall":64,"flags":["This job leans heavily on numerical reasoning — an area you rated yourself lower on.","Recognition matters to you, but this job doesn''t offer much of it."]}'),
 (9004,'job_fit',NULL,'{"occupation_id":"civil_engineering","overall":82,"flags":[]}'),
 (9005,'job_fit',NULL,'{"occupation_id":"architecture","overall":58,"flags":["This job leans heavily on numerical reasoning — an area you rated yourself lower on.","This job leans heavily on spatial reasoning — an area you rated yourself lower on."]}'),
 (9006,'job_fit',NULL,'{"occupation_id":"chartered_accountancy","overall":42,"flags":["Independence matters to you, but this job doesn''t offer much of it."]}'),
 (9012,'job_fit',NULL,'{"occupation_id":"electrician","overall":76,"flags":["This job involves physically demanding work. You said that would bother you."]}');
