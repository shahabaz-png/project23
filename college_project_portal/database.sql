-- College Project Management Portal - database schema
-- Import this file in phpMyAdmin (Import tab). It creates the database and all tables.

CREATE DATABASE IF NOT EXISTS college_project_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE college_project_portal;

CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('student','faculty','admin') NOT NULL DEFAULT 'student',
  student_number VARCHAR(30) NULL,
  department     VARCHAR(100) NULL,
  account_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_student_number (student_number),
  KEY idx_users_role (role),
  KEY idx_users_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         INT UNSIGNED NOT NULL,
  faculty_id         INT UNSIGNED NULL,
  title              VARCHAR(150) NOT NULL,
  category           VARCHAR(50) NOT NULL,
  description        TEXT NOT NULL,
  technology_stack   VARCHAR(255) NOT NULL,
  github_url         VARCHAR(255) NULL,
  live_demo_url      VARCHAR(255) NULL,
  file_name          VARCHAR(100) NOT NULL,   -- random name stored on the server
  original_file_name VARCHAR(150) NOT NULL,   -- cleaned name shown to users
  file_size          INT UNSIGNED NOT NULL DEFAULT 0,
  status             ENUM('Pending','Approved','Rejected','Revision Required') NOT NULL DEFAULT 'Pending',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  submitted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_student (student_id),
  KEY idx_projects_faculty (faculty_id),
  KEY idx_projects_status (status),
  KEY idx_projects_category (category),
  CONSTRAINT fk_projects_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projects_faculty FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_reviews (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id      INT UNSIGNED NOT NULL,
  reviewer_id     INT UNSIGNED NOT NULL,
  previous_status ENUM('Pending','Approved','Rejected','Revision Required') NULL,
  new_status      ENUM('Pending','Approved','Rejected','Revision Required') NOT NULL,
  feedback        TEXT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reviews_project (project_id),
  KEY idx_reviews_reviewer (reviewer_id),
  CONSTRAINT fk_reviews_project  FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
