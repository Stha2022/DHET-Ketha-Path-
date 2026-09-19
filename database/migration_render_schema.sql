-- Khetha Path: bring an existing live database (the TiDB Cloud one behind Render)
-- in line with the app code. Nothing is deleted.
--
-- Run once in the TiDB Cloud SQL Editor against the app's database.
--
-- 1. favourites and notifications have an older layout that every insert fails
--    against (favourites.careerID; no notifications.type / actionURL). Both were
--    empty on 2026-09-19. They are renamed to *_legacy, not dropped; drop the
--    *_legacy tables yourself once you are happy. Skip these two lines if the
--    tables already have itemType / actionURL.
-- 2. The tables the app needs are created if missing (definitions copied from
--    database/khetha_path.sql). This part is safe to re-run.

RENAME TABLE favourites TO favourites_legacy;
RENAME TABLE notifications TO notifications_legacy;

CREATE TABLE IF NOT EXISTS favourites (
    favouriteID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    userID INT UNSIGNED NOT NULL,
    itemType VARCHAR(40) NOT NULL,
    itemID VARCHAR(120) NOT NULL,
    label VARCHAR(190) NOT NULL DEFAULT '',
    metadata JSON NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (favouriteID),
    UNIQUE KEY uq_favourite (userID, itemType, itemID),
    KEY idx_favourite_user (userID, createdAt),
    CONSTRAINT fk_favourite_user FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    notificationID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    userID INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'journey',
    actionURL VARCHAR(255) NULL,
    scheduledAt DATETIME NULL,
    readAt DATETIME NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notificationID),
    KEY idx_notifications_user (userID, readAt, scheduledAt, createdAt),
    CONSTRAINT fk_notification_user FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS learner_profiles (
    userID INT UNSIGNED NOT NULL,
    profileData JSON NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (userID),
    CONSTRAINT fk_profile_user FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
    userID INT UNSIGNED NOT NULL,
    pushEnabled TINYINT(1) NOT NULL DEFAULT 0,
    deadlineReminders TINYINT(1) NOT NULL DEFAULT 1,
    assessmentReminders TINYINT(1) NOT NULL DEFAULT 1,
    journeyTips TINYINT(1) NOT NULL DEFAULT 1,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (userID),
    CONSTRAINT fk_notification_pref_user FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS learner_content_interactions (
    interactionID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    userID INT UNSIGNED NOT NULL,
    eventType VARCHAR(80) NOT NULL,
    eventData JSON NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (interactionID),
    KEY idx_interaction_user (userID, createdAt),
    CONSTRAINT fk_interaction_user FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advisor_requests (
    requestID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    userID INT UNSIGNED NOT NULL,
    topic VARCHAR(60) NOT NULL,
    note TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'submitted',
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (requestID),
    KEY idx_advisor_user (userID, createdAt),
    CONSTRAINT fk_advisor_user FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

