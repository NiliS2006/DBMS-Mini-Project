# OPBMS — Organizational and Personal Budget Management System

A full-stack, database-driven web application for managing organizational and personal finances.  
Built with **PHP 8+**, **MySQL**, and vanilla HTML/CSS/JS — designed to run locally via **XAMPP**.

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Database Configuration](#database-configuration)
- [Running the Application](#running-the-application)
- [Website and Collaboration Guide](#website-and-collaboration-guide)
- [Demo Credentials](#demo-credentials)
- [Features by Role](#features-by-role)
- [Database Schema](#database-schema)
- [Known Limitations](#known-limitations)

---

## Prerequisites

| Requirement | Version |
|---|---|
| [XAMPP](https://www.apachefriends.org/) | 8.x or later |
| PHP | 8.0+ (bundled with XAMPP) |
| MySQL | 5.7+ or 8.0+ (bundled with XAMPP) |
| Browser | Chrome / Firefox / Edge (latest) |

---

## Project Structure

```
DBMS Mini Project/
├── DBMS_Mini_Project.pdf          # Project specification document
└── opbms/                         # Main application directory
    ├── index.php                  # Public landing page; signed-in users go to their dashboard
    ├── contact.php                # Contact form handler
    ├── dashboard.php              # Organizational dashboard
    ├── admin/
    │   └── users.php              # Admin: user management
    ├── approvals/
    │   ├── index.php              # Expense approval queue
    │   └── review.php            # Approve or reject an expense
    ├── assets/
    │   ├── css/style.css          # Global stylesheet
    │   ├── js/main.js             # Global JavaScript
    │   └── uploads/.htaccess      # Denies direct receipt-file access
    ├── auth/
    │   ├── login.php              # Login page
    │   ├── register.php           # Personal account registration
    │   └── logout.php             # Logout handler
    ├── budgets/
    │   ├── index.php              # Budget list
    │   ├── create.php             # Create budget
    │   └── approve.php            # Approve proposed budgets
    ├── config/
    │   └── database.php           # PDO connection (edit credentials here)
    ├── database/
    │   ├── opbms.sql              # Full schema + seed data
    │   └── migrations/            # Ordered updates for existing databases
    ├── departments/
    │   ├── index.php / create.php / edit.php / view.php
    ├── expenses/
    │   ├── index.php / create.php / edit.php / view.php / receipt.php
    ├── income/
    │   ├── index.php / create.php
    ├── includes/
    │   ├── auth.php               # Session helpers, CSRF
    │   ├── functions.php          # Utility functions
    │   ├── header.php             # HTML head + topbar
    │   ├── sidebar.php            # Role-aware navigation
    │   ├── footer.php             # Closing HTML
    │   └── 403.php               # Access denied page
    ├── organization/
    │   ├── index.php / create.php / edit.php / view.php
    ├── personal/
    │   ├── dashboard.php          # Personal finance overview
    │   ├── transactions.php       # Income & expense transactions
    │   ├── budgets.php            # Monthly budget limits
    │   └── reports.php            # Personal reports & charts
    ├── projects/
    │   ├── index.php / create.php / edit.php / view.php
    ├── docs/
    │   └── WEBSITE.md             # Website and collaboration guide
    └── reports/
        └── index.php              # Organizational financial reports
```

---

## Installation

### Step 1 — Install XAMPP

Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/).  
Start **Apache** and **MySQL** from the XAMPP Control Panel.

### Step 2 — Place the project in `htdocs`

Copy the entire `opbms` folder into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\opbms\
```

> **Important:** The folder must be named exactly `opbms` — this matches the `BASE_URL` constant used throughout the application.

### Step 3 — Import the database

1. Open your browser and go to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **New** in the left sidebar to create an empty database named `opbms`, using `utf8mb4_unicode_ci`.
3. Select the new `opbms` database, then click the **Import** tab.
4. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\opbms\database\opbms.sql
   ```
5. Click **Go** to import. The schema and demo data will be loaded.

> **Existing database:** Do not re-import `opbms.sql` into a database that already contains project data; its seed inserts are for a fresh database. Back up the existing database first, review the preflight query results in `database/migrations/001_enforce_transaction_hierarchy.sql`, then apply migrations `001`–`004` in order. Resolve any nonzero hierarchy validation counts before applying migration `001`. Migration `004` repairs only malformed hashes for the seeded demo accounts; valid password hashes are preserved.

### Step 4 — Configure the database connection

Open the file:
```
C:\xampp\htdocs\opbms\config\database.php
```

Update the credentials if needed (defaults work for a fresh XAMPP install):

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);       // Use 3307 if you configured XAMPP MySQL on port 3307
define('DB_NAME', 'opbms');
define('DB_USER', 'root');    // change if your MySQL user is different
define('DB_PASS', '');        // change if you set a MySQL password
```

If port `3306` is already in use, configure XAMPP MySQL and phpMyAdmin to use a free port, then set the same value for `DB_PORT` here. Also set `DB_USER` and `DB_PASS` to the credentials for that MySQL server.

---

## Running the Application

1. Ensure **Apache** and **MySQL** are running in the XAMPP Control Panel.
2. Open your browser and navigate to:

   **[http://localhost/opbms](http://localhost/opbms)**

3. Use **Log in** to access an existing account or **Get started** to create a personal account.
4. For testing, use any of the demo credentials below to log in.

The public home page is available at `http://localhost/opbms/`. Visitors can create a **personal** account from the sign-up page; organizational roles remain administrator-managed. The contact form requires `OPBMS_CONTACT_EMAIL` to be set in the PHP/Apache environment and PHP `mail()` to be configured. If mail delivery is not configured, the form reports that instead of claiming the message was sent.

---

## Website and Collaboration Guide

The public landing page and sign-up flow are documented in [`opbms/docs/WEBSITE.md`](opbms/docs/WEBSITE.md), including the frontend structure, contact-mail configuration, testing notes, and safe collaboration practices.

---

## Demo Credentials

> **Password for all accounts:** `password`

| Role | Name | Email | Access Level |
|---|---|---|---|
| **Admin** | Alice Admin | `admin@opbms.com` | Full system access |
| **Dept Manager** | David DeptMgr | `dept@opbms.com` | Assigned departments/projects, scoped approvals, budget proposals |
| **Dept Manager 2** | Raj DeptMgr2 | `dept2@opbms.com` | Assigned departments/projects, scoped approvals, budget proposals |
| **Project Manager** | Paul ProjMgr | `proj@opbms.com` | Assigned projects, scoped approvals, budget proposals |
| **Project Manager 2** | Priya ProjMgr2 | `proj2@opbms.com` | Assigned projects, scoped approvals, budget proposals |
| **Employee** | Emma Employee | `employee@opbms.com` | Submit, edit, cancel, and view own expenses; personal finance |
| **Employee 2** | Sam Employee2 | `employee2@opbms.com` | Submit, edit, cancel, and view own expenses; personal finance |
| **Personal User** | Nina Personal | `personal@opbms.com` | Personal finance module only |

---

## Features by Role

### Admin
- Full organizational dashboard with KPIs and charts
- **Organization management** — create, edit, view organizations
- **Department management** — create, edit, view, assign managers
- **User management** (`/admin/users.php`) — create users, change roles, activate/deactivate, reset passwords
- All project, budget, expense, income management
- **Financial reports** with Chart.js visualizations

### Department Manager
- Assigned department and project oversight
- Propose departmental/project budgets for administrator approval
- Scoped expense approval queue (cannot approve own submissions)
- Income recording
- Financial reports

### Project Manager
- Assigned-project oversight and budget proposals
- Expense submission and approval for assigned projects (cannot approve own submissions)
- Financial reports

### Employee
- Submit, edit, cancel, and track own pending expenses
- View personal expense history
- Personal finance module (transactions, budgets, reports)

### Personal User
- Personal finance dashboard (income/expense summary)
- Transaction management (add income & expense entries)
- Monthly budget limits by category
- Personal reports with charts (income vs expense, category breakdown, budget vs actual)

---

## Database Schema

The database uses **12 tables** and **2 views**:

| Table | Description |
|---|---|
| `users` | All system users with role and active status |
| `organizations` | Top-level organizations |
| `departments` | Departments belonging to organizations |
| `projects` | Projects under departments |
| `project_members` | Many-to-many: users assigned to projects |
| `budgets` | Budget allocations (org, dept, or project level) |
| `expense_categories` | Shared categories for both organizational and personal expenses |
| `expenses` | Organizational expense submissions with approval workflow |
| `approval_history` | Audit trail for all expense approval actions |
| `income_records` | Organizational income entries |
| `personal_transactions` | Personal income and expense transactions |
| `personal_budgets` | Monthly budget limits per user per category |

### Views

| View | Description |
|---|---|
| `v_project_budget_summary` | Year-specific project budget, approved/pending spending, and utilization |
| `v_dept_budget_summary` | Year-specific department budget, approved spending, and utilization |

### Entity Hierarchy

```
Organization
  └── Department
        └── Project
              └── Budget ──► Expenses (approved/pending/rejected)
                            Income Records
```

### Key Design Decisions

- All monetary values use `DECIMAL(15,2)` for precision
- `expenses.status` ENUM controls the workflow: `pending → approved / rejected / cancelled`
- `budgets.entity_type` ENUM (`organization`, `department`, `project`) allows a single table to serve all budget levels
- `approval_history` provides a complete audit trail separate from the `expenses` table
- Composite foreign keys enforce that selected department, organization, and project references agree on organizational transactions
- `personal_transactions` and `personal_budgets` are entirely user-scoped (no cross-user access)
- Pending expense edits and cancellations are recorded in approval history; cancelled expenses are excluded from approved expenditure
- All foreign keys use `ON DELETE CASCADE`, `ON DELETE SET NULL`, or `ON DELETE RESTRICT` as appropriate
- Sessions expire after 30 minutes of inactivity, and account activation/role changes are checked against MySQL on authenticated requests

---

## Known Limitations

1. **No email notifications** — Password resets and approval notifications are in-app only; no SMTP integration.
2. **No pagination on large tables** — Several table listings fetch all matching records. For production use, add `LIMIT/OFFSET` pagination.
3. **Receipt storage depends on Apache overrides** — Receipts are served through an access-checked PHP endpoint and direct access is denied by `assets/uploads/.htaccess`. Ensure Apache allows this `.htaccess` file to take effect.
4. **Demo credentials are not production credentials** — All seeded accounts share the password `password`. Change or remove these accounts before deployment.
5. **HTTPS is not configured by the local XAMPP setup** — Use HTTPS and secure session-cookie settings before exposing the application outside a trusted local environment.
6. **Runtime integration tests require a configured MySQL account** — Confirm `config/database.php` credentials match the local MySQL installation before testing workflows.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3 (custom design system), JavaScript, Chart.js 4.4 |
| Backend | PHP 8+ with PDO and prepared statements |
| Database | MySQL 5.7+ / 8.0+ |
| Icons | Font Awesome 6.5 |
| Fonts | Inter (Google Fonts) |
| Local Env | XAMPP (Apache + MySQL) |

---

*OPBMS — B.Tech ISE DBMS Laboratory Mini Project*
