CREATE DATABASE IF NOT EXISTS khetha_path CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE khetha_path;
CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,grade VARCHAR(30),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS assessments(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,subjects TEXT,interests TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS careers(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL UNIQUE,description TEXT,source VARCHAR(255) DEFAULT 'NCAP / DHET');
CREATE TABLE IF NOT EXISTS pathways(id INT AUTO_INCREMENT PRIMARY KEY,career_id INT NOT NULL,title VARCHAR(150) NOT NULL,description TEXT,source VARCHAR(255) DEFAULT 'NCAP / DHET',FOREIGN KEY(career_id) REFERENCES careers(id) ON DELETE CASCADE);
INSERT IGNORE INTO careers(name,description) VALUES('Software Development','Build websites, applications and digital products.'),('Cybersecurity','Protect systems, networks and information.'),('Data Analytics','Use data to find patterns and support decisions.');
