USE khetha_path;

-- Run this migration ONLY if you already created the old `users` table
-- that did not contain email/password_hash.
ALTER TABLE users
    ADD COLUMN email VARCHAR(190) NULL AFTER name,
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER email;

-- Existing prototype rows (if any) will have NULL credentials.
-- After confirming the new registration works, make the fields required:
-- ALTER TABLE users MODIFY email VARCHAR(190) NOT NULL;
-- ALTER TABLE users MODIFY password_hash VARCHAR(255) NOT NULL;
-- ALTER TABLE users ADD UNIQUE KEY uq_users_email (email);
