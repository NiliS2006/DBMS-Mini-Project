-- ============================================================
-- OPBMS: Organizational and Personal Budget Management System
-- Database Schema + Seed Data
-- MySQL 5.7+ / 8.0+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS opbms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE opbms;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120)  NOT NULL,
    email         VARCHAR(180)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('admin','dept_manager','project_manager','employee','personal') NOT NULL DEFAULT 'employee',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organizations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200)  NOT NULL,
    description   TEXT,
    address       TEXT,
    contact_email VARCHAR(180),
    created_by    INT UNSIGNED  NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED  NOT NULL,
    name            VARCHAR(200)  NOT NULL,
    description     TEXT,
    manager_id      INT UNSIGNED,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id)      REFERENCES users(id)         ON DELETE SET NULL,
    INDEX idx_dept_org (organization_id),
    INDEX idx_dept_mgr (manager_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id   INT UNSIGNED  NOT NULL,
    name            VARCHAR(200)  NOT NULL,
    description     TEXT,
    manager_id      INT UNSIGNED,
    start_date      DATE,
    end_date        DATE,
    status          ENUM('active','completed','on_hold','cancelled') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id)    REFERENCES users(id)       ON DELETE SET NULL,
    INDEX idx_proj_dept (department_id),
    INDEX idx_proj_mgr  (manager_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_members (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    role       ENUM('manager','member') NOT NULL DEFAULT 'member',
    added_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_proj_user (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS budgets (
    id                INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    entity_type       ENUM('organization','department','project') NOT NULL,
    entity_id         INT UNSIGNED  NOT NULL,
    amount            DECIMAL(15,2) NOT NULL,
    fiscal_year       YEAR          NOT NULL,
    period_start      DATE          NOT NULL,
    period_end        DATE          NOT NULL,
    status            ENUM('proposed','approved','revised') NOT NULL DEFAULT 'proposed',
    approved_by       INT UNSIGNED,
    approved_at       TIMESTAMP     NULL,
    notes             TEXT,
    created_by        INT UNSIGNED  NOT NULL,
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_budget_entity (entity_type, entity_id),
    INDEX idx_budget_year   (fiscal_year)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expense_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    type        ENUM('organizational','personal','both') NOT NULL DEFAULT 'both',
    description VARCHAR(255),
    is_active   TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255)  NOT NULL,
    description     TEXT,
    amount          DECIMAL(15,2) NOT NULL,
    category_id     INT UNSIGNED,
    expense_date    DATE          NOT NULL,
    organization_id INT UNSIGNED,
    department_id   INT UNSIGNED,
    project_id      INT UNSIGNED,
    submitted_by    INT UNSIGNED  NOT NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by     INT UNSIGNED,
    approved_at     TIMESTAMP     NULL,
    rejection_reason TEXT,
    receipt_path    VARCHAR(500),
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id)     REFERENCES expense_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id)      ON DELETE CASCADE,
    FOREIGN KEY (department_id)   REFERENCES departments(id)        ON DELETE CASCADE,
    FOREIGN KEY (project_id)      REFERENCES projects(id)           ON DELETE CASCADE,
    FOREIGN KEY (submitted_by)    REFERENCES users(id)              ON DELETE RESTRICT,
    FOREIGN KEY (approved_by)     REFERENCES users(id)              ON DELETE SET NULL,
    INDEX idx_exp_proj      (project_id),
    INDEX idx_exp_dept      (department_id),
    INDEX idx_exp_org       (organization_id),
    INDEX idx_exp_status    (status),
    INDEX idx_exp_date      (expense_date),
    INDEX idx_exp_submitter (submitted_by)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id  INT UNSIGNED  NOT NULL,
    action      ENUM('submitted','approved','rejected','resubmitted') NOT NULL,
    action_by   INT UNSIGNED  NOT NULL,
    action_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes       TEXT,
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by)  REFERENCES users(id)    ON DELETE RESTRICT,
    INDEX idx_approval_expense (expense_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS income_records (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    source          VARCHAR(255)  NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    income_date     DATE          NOT NULL,
    description     TEXT,
    organization_id INT UNSIGNED,
    department_id   INT UNSIGNED,
    project_id      INT UNSIGNED,
    recorded_by     INT UNSIGNED  NOT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id)   REFERENCES departments(id)   ON DELETE CASCADE,
    FOREIGN KEY (project_id)      REFERENCES projects(id)      ON DELETE CASCADE,
    FOREIGN KEY (recorded_by)     REFERENCES users(id)         ON DELETE RESTRICT,
    INDEX idx_income_org  (organization_id),
    INDEX idx_income_date (income_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS personal_budgets (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED  NOT NULL,
    category_id  INT UNSIGNED  NOT NULL,
    amount       DECIMAL(15,2) NOT NULL,
    month        TINYINT       NOT NULL,
    year         YEAR          NOT NULL,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_personal_budget (user_id, category_id, month, year),
    FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE CASCADE,
    INDEX idx_pb_user   (user_id),
    INDEX idx_pb_period (year, month)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS personal_transactions (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED  NOT NULL,
    type             ENUM('income','expense') NOT NULL,
    amount           DECIMAL(15,2) NOT NULL,
    category_id      INT UNSIGNED,
    description      VARCHAR(500),
    transaction_date DATE         NOT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
    INDEX idx_pt_user (user_id),
    INDEX idx_pt_date (transaction_date),
    INDEX idx_pt_type (type)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Users (password for all: password)
-- password_hash('password') in PHP = $2y.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (name, email, password_hash, role) VALUES
('Alice Admin',    'admin@opbms.com',     '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('David DeptMgr',  'dept@opbms.com',      '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dept_manager'),
('Paul ProjMgr',   'proj@opbms.com',      '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'project_manager'),
('Emma Employee',  'employee@opbms.com',  '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee'),
('Sam Employee2',  'employee2@opbms.com', '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee'),
('Nina Personal',  'personal@opbms.com',  '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'personal'),
('Raj DeptMgr2',   'dept2@opbms.com',     '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dept_manager'),
('Priya ProjMgr2', 'proj2@opbms.com',     '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'project_manager');

INSERT INTO organizations (name, description, address, contact_email, created_by) VALUES
('TechCorp Pvt Ltd',     'Leading technology solutions company',    '123 Tech Park, Bengaluru, Karnataka 560001', 'info@techcorp.com',    1),
('EduGrowth Foundation', 'Non-profit educational organization',     '45 Learning Lane, Mysuru, Karnataka 570002', 'contact@edugrowth.org',1);

INSERT INTO departments (organization_id, name, description, manager_id) VALUES
(1,'Engineering',       'Software engineering and R&D',      2),
(1,'Marketing',         'Marketing and brand management',    7),
(1,'Human Resources',   'HR and people operations',         NULL),
(2,'Academic Programs', 'Course design and delivery',        2),
(2,'Operations',        'Day-to-day operational activities', 7);

INSERT INTO projects (department_id, name, description, manager_id, start_date, end_date, status) VALUES
(1,'ERP System Development','Building internal ERP system',            3,'2026-01-01','2026-12-31','active'),
(1,'Mobile App Redesign',   'Redesigning mobile application UI/UX',   8,'2026-03-01','2026-09-30','active'),
(2,'Q4 Marketing Campaign', 'Digital marketing campaign for Q4 2026', 3,'2026-10-01','2026-12-31','active'),
(4,'Online Course Platform','LMS platform development',                8,'2026-02-01','2026-11-30','active'),
(5,'Annual Conference 2026','Organizing annual education conference',  3,'2026-09-01','2026-11-30','active');

INSERT INTO project_members (project_id, user_id, role) VALUES
(1,3,'manager'),(1,4,'member'),(1,5,'member'),
(2,8,'manager'),(2,4,'member'),
(3,3,'manager'),(3,5,'member'),
(4,8,'manager'),(4,4,'member'),
(5,3,'manager'),(5,5,'member');

INSERT INTO expense_categories (name, type, description) VALUES
('Travel & Transport',   'both',           'Travel, transport, and accommodation expenses'),
('Office Supplies',      'both',           'Stationery, equipment, and office consumables'),
('Software & Licenses',  'organizational', 'Software subscriptions and license fees'),
('Marketing & Ads',      'organizational', 'Advertising, promotions, and branding'),
('Training & Education', 'both',           'Training programs, workshops, and courses'),
('Utilities',            'organizational', 'Electricity, internet, and utility bills'),
('Salaries',             'organizational', 'Staff salaries and compensation'),
('Food & Dining',        'both',           'Meals, dining, and food expenses'),
('Healthcare',           'both',           'Medical and healthcare expenses'),
('Rent & Accommodation', 'both',           'Rent and housing expenses'),
('Shopping',             'personal',       'Personal shopping and retail'),
('Entertainment',        'personal',       'Entertainment and leisure'),
('Other',                'both',           'Miscellaneous expenses');

INSERT INTO budgets (entity_type, entity_id, amount, fiscal_year, period_start, period_end, status, approved_by, approved_at, created_by) VALUES
('organization',1,5000000.00,2026,'2026-01-01','2026-12-31','approved',1,'2026-01-05 10:00:00',1),
('organization',2,2000000.00,2026,'2026-01-01','2026-12-31','approved',1,'2026-01-05 10:00:00',1),
('department',1,2000000.00,2026,'2026-01-01','2026-12-31','approved',1,'2026-01-10 10:00:00',1),
('department',2, 800000.00,2026,'2026-01-01','2026-12-31','approved',1,'2026-01-10 10:00:00',1),
('department',4, 900000.00,2026,'2026-01-01','2026-12-31','approved',1,'2026-01-10 10:00:00',1),
('project',1,1200000.00,2026,'2026-01-01','2026-12-31','approved',1,'2026-01-15 10:00:00',1),
('project',2, 500000.00,2026,'2026-03-01','2026-09-30','approved',1,'2026-03-01 10:00:00',1),
('project',3, 600000.00,2026,'2026-10-01','2026-12-31','approved',1,'2026-10-01 10:00:00',1),
('project',4, 750000.00,2026,'2026-02-01','2026-11-30','approved',1,'2026-02-01 10:00:00',1),
('project',5, 300000.00,2026,'2026-09-01','2026-11-30','approved',1,'2026-09-01 10:00:00',1);

INSERT INTO expenses (title, description, amount, category_id, expense_date, organization_id, department_id, project_id, submitted_by, status, approved_by, approved_at) VALUES
('AWS Cloud Services - Jan','Monthly AWS infrastructure costs',  45000.00,3,'2026-01-31',1,1,1,4,'approved',3,'2026-02-02 09:00:00'),
('Developer Laptops x3',    'High-end laptops for new developers',120000.00,2,'2026-02-05',1,1,1,4,'approved',3,'2026-02-07 11:00:00'),
('Team Dinner - Sprint End','Sprint completion team dinner',      8500.00,8,'2026-02-28',1,1,1,5,'approved',3,'2026-03-01 10:00:00'),
('AWS Cloud Services - Feb','Monthly AWS infrastructure costs',  45000.00,3,'2026-02-28',1,1,1,4,'approved',3,'2026-03-02 09:00:00'),
('Training - DevOps Workshop','Team DevOps training program',    35000.00,5,'2026-03-10',1,1,1,5,'approved',3,'2026-03-12 10:00:00'),
('AWS Cloud Services - Mar','Monthly AWS infrastructure costs',  48000.00,3,'2026-03-31',1,1,1,4,'approved',3,'2026-04-01 09:00:00'),
('Office Stationery Q1',    'Stationery and office supplies',    12000.00,2,'2026-03-31',1,1,1,5,'approved',3,'2026-04-02 10:00:00'),
('Figma Enterprise License','Annual Figma design tool license',  25000.00,3,'2026-03-15',1,1,2,4,'approved',8,'2026-03-16 10:00:00'),
('UI/UX Consultant Fee',    'External UX consultant for 2 weeks',60000.00,13,'2026-04-30',1,1,2,4,'approved',8,'2026-05-02 10:00:00'),
('Google Ads - Oct',        'Google Ads campaign budget',        80000.00,4,'2026-10-31',1,2,3,5,'approved',3,'2026-11-01 10:00:00'),
('Facebook Ads - Oct',      'Facebook/Instagram campaign',       50000.00,4,'2026-10-31',1,2,3,5,'approved',3,'2026-11-01 10:00:00'),
('Marketing Collaterals',   'Printing brochures and banners',    25000.00,4,'2026-10-15',1,2,3,5,'approved',3,'2026-10-16 09:00:00'),
('AWS Cloud Services - Oct','Monthly AWS infrastructure costs',  50000.00,3,'2026-10-31',1,1,1,4,'pending',NULL,NULL),
('Team Travel - Conference','Travel for tech conference Mumbai', 35000.00,1,'2026-10-20',1,1,1,5,'pending',NULL,NULL),
('Marketing Analytics Tool','Monthly analytics subscription',    15000.00,3,'2026-10-01',1,2,3,5,'pending',NULL,NULL),
('Luxury Team Outing',      'Team outing at luxury resort',     150000.00,13,'2026-09-15',1,1,1,4,'rejected',3,'2026-09-17 10:00:00');

UPDATE expenses SET rejection_reason = 'Exceeds entertainment budget policy. Please submit a revised, cost-appropriate proposal.' WHERE id = 16;

INSERT INTO approval_history (expense_id, action, action_by, action_at, notes) VALUES
(1,'submitted',4,'2026-01-31 17:00:00',NULL),
(1,'approved',3,'2026-02-02 09:00:00','Approved - recurring infrastructure cost'),
(2,'submitted',4,'2026-02-05 14:00:00',NULL),
(2,'approved',3,'2026-02-07 11:00:00','Approved - necessary hardware'),
(3,'submitted',5,'2026-02-28 18:00:00',NULL),
(3,'approved',3,'2026-03-01 10:00:00','Approved'),
(4,'submitted',4,'2026-02-28 17:00:00',NULL),
(4,'approved',3,'2026-03-02 09:00:00','Approved'),
(5,'submitted',5,'2026-03-10 10:00:00',NULL),
(5,'approved',3,'2026-03-12 10:00:00','Approved - skill development'),
(6,'submitted',4,'2026-03-31 17:00:00',NULL),
(6,'approved',3,'2026-04-01 09:00:00','Approved'),
(7,'submitted',5,'2026-03-31 16:00:00',NULL),
(7,'approved',3,'2026-04-02 10:00:00','Approved'),
(8,'submitted',4,'2026-03-15 12:00:00',NULL),
(8,'approved',8,'2026-03-16 10:00:00','Approved'),
(9,'submitted',4,'2026-04-30 17:00:00',NULL),
(9,'approved',8,'2026-05-02 10:00:00','Approved - quality consultant'),
(10,'submitted',5,'2026-10-31 17:00:00',NULL),
(10,'approved',3,'2026-11-01 10:00:00','Approved'),
(11,'submitted',5,'2026-10-31 17:00:00',NULL),
(11,'approved',3,'2026-11-01 10:00:00','Approved'),
(12,'submitted',5,'2026-10-15 12:00:00',NULL),
(12,'approved',3,'2026-10-16 09:00:00','Approved'),
(13,'submitted',4,'2026-10-31 17:00:00',NULL),
(14,'submitted',5,'2026-10-20 12:00:00',NULL),
(15,'submitted',5,'2026-10-01 09:00:00',NULL),
(16,'submitted',4,'2026-09-15 12:00:00',NULL),
(16,'rejected',3,'2026-09-17 10:00:00','Exceeds entertainment budget policy');

INSERT INTO income_records (source, amount, income_date, description, organization_id, department_id, project_id, recorded_by) VALUES
('Client Project - Infosys',   800000.00,'2026-01-15','Milestone 1 payment for ERP project',         1,1,1,1),
('Client Project - Infosys',   600000.00,'2026-04-20','Milestone 2 payment for ERP project',         1,1,1,1),
('Product License Revenue',    150000.00,'2026-03-31','Q1 software license sales',                   1,NULL,NULL,1),
('Marketing Retainer - Wipro', 200000.00,'2026-10-05','Monthly retainer from Wipro marketing',       1,2,3,1),
('Government Grant',           500000.00,'2026-02-01','Education department grant for Q1',           2,4,4,1),
('Course Fees - Batch 1',      180000.00,'2026-03-15','Registration fees from first batch',          2,4,4,1),
('Conference Sponsorship',     120000.00,'2026-09-10','Sponsorship from industry partners',          2,5,5,1);

INSERT INTO personal_transactions (user_id, type, amount, category_id, description, transaction_date) VALUES
(6,'income',65000.00,NULL,'Monthly Salary - October 2026',   '2026-10-01'),
(6,'income', 5000.00,NULL,'Freelance project payment',       '2026-10-15'),
(6,'income',65000.00,NULL,'Monthly Salary - September 2026', '2026-09-01'),
(6,'income',65000.00,NULL,'Monthly Salary - August 2026',    '2026-08-01'),
(6,'expense',15000.00,10,'October Rent',                     '2026-10-01'),
(6,'expense', 3200.00, 8,'Weekly groceries x4',              '2026-10-05'),
(6,'expense', 1800.00, 1,'Monthly bus pass',                 '2026-10-03'),
(6,'expense',  950.00, 8,'Restaurant dinner',                '2026-10-12'),
(6,'expense', 2500.00,11,'Online shopping - clothes',        '2026-10-18'),
(6,'expense', 1200.00,12,'Netflix and Spotify subscriptions','2026-10-05'),
(6,'expense', 3500.00, 5,'Online course subscription',       '2026-10-20'),
(6,'expense', 1500.00, 9,'Pharmacy - medicines',             '2026-10-22'),
(6,'expense',15000.00,10,'September Rent',                   '2026-09-01'),
(6,'expense', 2800.00, 8,'Groceries',                        '2026-09-08'),
(6,'expense', 1800.00, 1,'Monthly bus pass',                 '2026-09-02'),
(6,'expense', 4500.00,12,'Weekend trip entertainment',       '2026-09-22'),
(6,'expense', 2200.00,11,'Shopping - electronics accessories','2026-09-25'),
(6,'expense',15000.00,10,'August Rent',                      '2026-08-01'),
(6,'expense', 3100.00, 8,'Groceries',                        '2026-08-07'),
(6,'expense', 1800.00, 1,'Monthly bus pass',                 '2026-08-03'),
(6,'expense', 8000.00, 9,'Health checkup and medicines',     '2026-08-15'),
(6,'expense', 1200.00,12,'Streaming subscriptions',          '2026-08-05');

INSERT INTO personal_budgets (user_id, category_id, amount, month, year) VALUES
(6,10,16000.00,10,2026),
(6, 8, 5000.00,10,2026),
(6, 1, 2000.00,10,2026),
(6,11, 2000.00,10,2026),
(6,12, 1500.00,10,2026),
(6, 5, 4000.00,10,2026),
(6, 9, 2000.00,10,2026);

CREATE OR REPLACE VIEW v_project_budget_summary AS
SELECT
    p.id   AS project_id,
    p.name AS project_name,
    p.status,
    d.name AS department_name,
    o.name AS org_name,
    COALESCE(b.amount, 0) AS allocated_budget,
    COALESCE(SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END),0) AS approved_expenditure,
    COALESCE(b.amount,0) - COALESCE(SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END),0) AS remaining_budget,
    CASE WHEN COALESCE(b.amount,0)>0
         THEN ROUND(COALESCE(SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END),0)/b.amount*100,2)
         ELSE 0 END AS utilization_pct,
    COALESCE(SUM(CASE WHEN e.status='pending' THEN e.amount ELSE 0 END),0) AS pending_amount
FROM projects p
JOIN departments d   ON d.id=p.department_id
JOIN organizations o ON o.id=d.organization_id
LEFT JOIN budgets b  ON b.entity_type='project' AND b.entity_id=p.id AND b.status='approved'
LEFT JOIN expenses e ON e.project_id=p.id
GROUP BY p.id,p.name,p.status,d.name,o.name,b.amount;

CREATE OR REPLACE VIEW v_dept_budget_summary AS
SELECT
    d.id   AS dept_id,
    d.name AS dept_name,
    o.name AS org_name,
    COALESCE(b.amount,0) AS allocated_budget,
    COALESCE(SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END),0) AS approved_expenditure,
    COALESCE(b.amount,0) - COALESCE(SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END),0) AS remaining_budget,
    CASE WHEN COALESCE(b.amount,0)>0
         THEN ROUND(COALESCE(SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END),0)/b.amount*100,2)
         ELSE 0 END AS utilization_pct
FROM departments d
JOIN organizations o ON o.id=d.organization_id
LEFT JOIN budgets b  ON b.entity_type='department' AND b.entity_id=d.id AND b.status='approved'
LEFT JOIN expenses e ON e.department_id=d.id
GROUP BY d.id,d.name,o.name,b.amount;
