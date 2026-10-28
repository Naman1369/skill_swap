-- ============================================================
--  SkillSwap: Student Skill Exchange Platform
--  XAMPP / local install: DROPS and recreates the whole database.
--  GENERATED from database/schema.sql: edit that file, not this one.
--
--  Import with phpMyAdmin (Import tab) or from the command line:
--    C:\xampp\mysql\bin\mysql.exe -u root < database\skillswap.sql
--  Then (optional) load demo data:
--    C:\xampp\php\php.exe database\seed.php
--  Production / Docker uses database/install.php instead (never drops anything).
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS skillswap
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE skillswap;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS admin_audit;
DROP TABLE IF EXISTS exchange_requests;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
--  SkillSwap schema (idempotent: safe to run again and again)
--  Used by database/install.php (production / Docker) and,
--  with a DROP header, by database/skillswap.sql (XAMPP).
--  Keep each statement ending with a semicolon at the end of a line.
-- ============================================================

-- users: login identity plus the role used for access control
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(190)  NOT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('student','admin')      NOT NULL DEFAULT 'student',
  status        ENUM('active','suspended')   NOT NULL DEFAULT 'active',
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- profiles: one-to-one student details
CREATE TABLE IF NOT EXISTS profiles (
  user_id       INT UNSIGNED  NOT NULL,
  course        VARCHAR(100)  NULL,
  year_of_study VARCHAR(30)   NULL,
  college       VARCHAR(150)  NULL,
  bio           TEXT          NULL,
  phone         VARCHAR(20)   NULL,
  contact_link  VARCHAR(255)  NULL,
  avatar        VARCHAR(64)   NULL,
  avatar_mime   VARCHAR(20)   NULL,
  avatar_data   MEDIUMBLOB    NULL,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY idx_profiles_avatar (avatar),
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- categories: fixed list used to group skill listings
CREATE TABLE IF NOT EXISTS categories (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60)  NOT NULL,
  icon VARCHAR(16)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- skills: what a student can teach or wants to learn
CREATE TABLE IF NOT EXISTS skills (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title       VARCHAR(100) NOT NULL,
  description TEXT         NULL,
  type        ENUM('teach','learn')                       NOT NULL,
  level       ENUM('beginner','intermediate','advanced')  NOT NULL DEFAULT 'beginner',
  mode        ENUM('online','offline','both')             NOT NULL DEFAULT 'both',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_skills_type_category (type, category_id),
  KEY idx_skills_user (user_id),
  CONSTRAINT fk_skills_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_skills_category FOREIGN KEY (category_id)
    REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- exchange_requests: a student asks to learn another student's "teach" skill
-- Status flow: pending -> accepted | rejected | cancelled ; accepted -> completed
CREATE TABLE IF NOT EXISTS exchange_requests (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  sender_id        INT UNSIGNED  NOT NULL,
  receiver_id      INT UNSIGNED  NOT NULL,
  skill_id         INT UNSIGNED  NOT NULL,
  offered_skill_id INT UNSIGNED  NULL,
  message          VARCHAR(1000) NOT NULL,
  status           ENUM('pending','accepted','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_requests_receiver_status (receiver_id, status),
  KEY idx_requests_sender_status (sender_id, status),
  KEY idx_requests_skill (skill_id),
  CONSTRAINT fk_requests_sender FOREIGN KEY (sender_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_requests_receiver FOREIGN KEY (receiver_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_requests_skill FOREIGN KEY (skill_id)
    REFERENCES skills (id) ON DELETE CASCADE,
  CONSTRAINT fk_requests_offered_skill FOREIGN KEY (offered_skill_id)
    REFERENCES skills (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admin_audit: who did what in the admin area (admin name is kept even if the account is deleted later)
CREATE TABLE IF NOT EXISTS admin_audit (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id    INT UNSIGNED NULL,
  admin_name  VARCHAR(100) NOT NULL,
  action      VARCHAR(40)  NOT NULL,
  target_type VARCHAR(20)  NOT NULL,
  target_id   INT UNSIGNED NULL,
  detail      VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id)
    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rate_limits: counts failed logins, sign-ups and other throttled actions (identifiers are hashed)
CREATE TABLE IF NOT EXISTS rate_limits (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket     VARCHAR(20)     NOT NULL,
  ident      CHAR(64)        NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rate_lookup (bucket, ident, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sessions: PHP sessions live here, not on disk, so logins survive restarts and redeploys
CREATE TABLE IF NOT EXISTS sessions (
  id            VARCHAR(128)    NOT NULL,
  data          MEDIUMBLOB      NOT NULL,
  last_activity INT UNSIGNED    NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- reference data
INSERT IGNORE INTO categories (name, icon) VALUES
  ('Programming',     '💻'),
  ('Web Development', '🌐'),
  ('Design',          '🎨'),
  ('Data & AI',       '📊'),
  ('Communication',   '🎤'),
  ('Languages',       '🗣️'),
  ('Music & Arts',    '🎸'),
  ('Academics',       '📚'),
  ('Other',           '✨');
