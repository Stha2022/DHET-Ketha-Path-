CREATE DATABASE IF NOT EXISTS khetha_path;
USE khetha_path;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 grade VARCHAR(30),
 consent_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE assessments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 subjects JSON,
 interests TEXT,
 result_summary TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE journey_events (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 event_data JSON,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE content_sources (
 id INT AUTO_INCREMENT PRIMARY KEY,
 source_name VARCHAR(180) NOT NULL,
 source_type VARCHAR(80),
 version VARCHAR(80),
 approved BOOLEAN DEFAULT FALSE,
 last_verified DATE NULL
);

-- Production principle:
-- AI recommendations must be grounded in approved NCAP/DHET data,
-- with consent, auditability, explainability and human escalation.
