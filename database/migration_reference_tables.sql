-- Khetha Path — reference-data tables: Occupation, Qualification, Provider.
--
-- Normalizes occupation-data.php's kp_occupations() / kp_qualifications() /
-- kp_providers() PHP arrays (currently the only source of truth) into SQL,
-- so the app can eventually read this directory from the DB instead of a
-- hardcoded PHP file, without changing any of the *_data.php adapter
-- functions built on top of it (sj_occupations(), cq_match_occupations(),
-- jf_match_occupations()) — they all key off the same string ids used here.
--
-- Scoring dictionaries (riasec, work_values, work_context, aptitudes) stay
-- as JSON columns rather than being split into EAV child tables: they are
-- always read as one whole object per occupation (never queried key-by-
-- key), have a small fixed key set, and this project already stores
-- similarly-shaped data as JSON (see assessments.subjects and
-- journey_events.event_data in khetha_path.sql). Genuine one-to-many and
-- many-to-many relationships (alt titles, tags, pathways, subject
-- requirements, occupation<->qualification, qualification<->provider) are
-- normalized into real child/join tables since those are the parts
-- actually queried and joined (e.g. "occupations tagged high_demand",
-- "providers offering qualification X").
--
-- Run after khetha_path.sql. Idempotent (CREATE TABLE IF NOT EXISTS).

USE khetha_path;

CREATE TABLE IF NOT EXISTS occupations (
    id VARCHAR(80) PRIMARY KEY,                 -- e.g. 'chemical_engineering' — matches the slug already used as an array key throughout the PHP code
    ofo_code VARCHAR(40) NULL,
    title VARCHAR(180) NOT NULL,
    field VARCHAR(120) NOT NULL,
    description TEXT NULL,
    video_url VARCHAR(255) NULL,
    riasec JSON NULL,                           -- {"R":90,"I":100,"A":10,"S":10,"E":30,"C":40}
    work_values JSON NULL,                      -- {"achievement":85,...} — kp_work_value_keys()
    work_context JSON NULL,                     -- {"outdoors":30,...} — kp_work_context_keys()
    aptitudes JSON NULL,                        -- {"verbal":55,...} — kp_aptitude_keys()
    math_track VARCHAR(30) NULL,                -- mathematics | math_literacy | either | technical_maths
    min_aps SMALLINT UNSIGNED NULL,
    demand VARCHAR(20) NULL,                    -- high | medium | low
    salary_band VARCHAR(60) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS occupation_alt_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    occupation_id VARCHAR(80) NOT NULL,
    alt_title VARCHAR(180) NOT NULL,
    UNIQUE KEY uq_occ_alt_title (occupation_id, alt_title),
    CONSTRAINT fk_occ_alt_titles_occ FOREIGN KEY (occupation_id) REFERENCES occupations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS occupation_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    occupation_id VARCHAR(80) NOT NULL,
    tag VARCHAR(60) NOT NULL,                   -- green | high_demand | trade | ...
    UNIQUE KEY uq_occ_tag (occupation_id, tag),
    CONSTRAINT fk_occ_tags_occ FOREIGN KEY (occupation_id) REFERENCES occupations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS occupation_pathways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    occupation_id VARCHAR(80) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    pathway TEXT NOT NULL,
    CONSTRAINT fk_occ_pathways_occ FOREIGN KEY (occupation_id) REFERENCES occupations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS occupation_subject_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    occupation_id VARCHAR(80) NOT NULL,
    subject VARCHAR(120) NOT NULL,
    min_percent TINYINT UNSIGNED NULL,
    necessity ENUM('required', 'recommended') NOT NULL,
    UNIQUE KEY uq_occ_subject (occupation_id, subject),
    CONSTRAINT fk_occ_subject_reqs_occ FOREIGN KEY (occupation_id) REFERENCES occupations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS qualifications (
    id VARCHAR(80) PRIMARY KEY,                 -- e.g. 'beng_chem'
    saqa_id VARCHAR(40) NULL,
    title VARCHAR(200) NOT NULL,
    nqf_level TINYINT UNSIGNED NULL,
    field VARCHAR(120) NULL,
    entry_aps SMALLINT UNSIGNED NULL,
    duration VARCHAR(80) NULL,                  -- e.g. '4 years' — free text in the source data, kept as-is
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS qualification_entry_subject_minimums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qualification_id VARCHAR(80) NOT NULL,
    subject VARCHAR(120) NOT NULL,
    min_percent TINYINT UNSIGNED NOT NULL,
    CONSTRAINT fk_qual_subject_mins_qual FOREIGN KEY (qualification_id) REFERENCES qualifications(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS providers (
    id VARCHAR(80) PRIMARY KEY,                 -- e.g. 'uct'
    name VARCHAR(200) NOT NULL,
    type VARCHAR(20) NOT NULL,                  -- university | tvet | cet | private | seta
    website VARCHAR(255) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    nsfas_accredited BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS provider_campuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id VARCHAR(80) NOT NULL,
    province VARCHAR(60) NULL,
    town VARCHAR(120) NULL,
    lat DECIMAL(9, 6) NULL,
    lng DECIMAL(9, 6) NULL,
    CONSTRAINT fk_provider_campuses_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

-- Many-to-many: an occupation can lead to several qualifications, and the
-- same qualification (e.g. a generic BCom) can serve several occupations.
CREATE TABLE IF NOT EXISTS occupation_qualifications (
    occupation_id VARCHAR(80) NOT NULL,
    qualification_id VARCHAR(80) NOT NULL,
    PRIMARY KEY (occupation_id, qualification_id),
    CONSTRAINT fk_occ_quals_occ FOREIGN KEY (occupation_id) REFERENCES occupations(id) ON DELETE CASCADE,
    CONSTRAINT fk_occ_quals_qual FOREIGN KEY (qualification_id) REFERENCES qualifications(id) ON DELETE CASCADE
);

-- Many-to-many: a qualification can be offered by several providers, and a
-- provider offers several qualifications.
CREATE TABLE IF NOT EXISTS qualification_providers (
    qualification_id VARCHAR(80) NOT NULL,
    provider_id VARCHAR(80) NOT NULL,
    PRIMARY KEY (qualification_id, provider_id),
    CONSTRAINT fk_qual_providers_qual FOREIGN KEY (qualification_id) REFERENCES qualifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_qual_providers_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);
