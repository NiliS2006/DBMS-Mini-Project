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
    ├── index.php                  # Entry point — redirects to login or dashboard
    ├── dashboard.php              # Organizational dashboard
    ├── admin/
    │   └── users.php              # Admin: user management
    ├── approvals/
    │   ├── index.php              # Expense approval queue
    │   └── review.php            # Approve or reject an expense
    ├── assets/
    │   ├── css/style.css          # Global stylesheet
    │   └── js/main.js             # Global JavaScript
    ├── auth/
    │   ├── login.php              # Login page
    │   └── logout.php             # Logout handler
    ├── budgets/
    │   ├── index.php              # Budget list
    │   ├── create.php             # Create budget
    │   └── approve.php            # Approve/revise budget (AJAX endpoint)
    ├── config/
    │   └── database.php           # PDO connection (edit credentials here)
    ├── database/
    │   └── opbms.sql              # Full schema + seed data
    ├── departments/
    │   ├── index.php / create.php / edit.php / view.php
    ├── expenses/
    │   ├── index.php / create.php / view.php
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
2. Click **New** in the left sidebar to create a new database.
3. Enter the name `opbms`, choose `utf8mb4_unicode_ci`, and click **Create**.
4. Select the `opbms` database, then click the **Import** tab.
5. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\opbms\database\opbms.sql
   ```
6. Click **Go** to import. The schema and all seed data will be loaded.

### Step 4 — Configure the database connection

Open the file:
```
C:\xampp\htdocs\opbms\config\database.php
```

Update the credentials if needed (defaults work for a fresh XAMPP install):

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'opbms');
define('DB_USER', 'root');    // change if your MySQL user is different
define('DB_PASS', '');        // change if you set a MySQL password
```

---

## Running the Application

1. Ensure **Apache** and **MySQL** are running in the XAMPP Control Panel.
2. Open your browser and navigate to:

   **[http://localhost/opbms](http://localhost/opbms)**

3. You will be redirected to the login page.
4. Use any of the demo credentials below to log in.

---

## Demo Credentials

> **Password for all accounts:** `password`

| Role | Name | Email | Access Level |
|---|---|---|---|
| **Admin** | Alice Admin | `admin@opbms.com` | Full system access |
| **Dept Manager** | David DeptMgr | `dept@opbms.com` | Dept + project management, approvals |
| **Dept Manager 2** | Raj DeptMgr2 | `dept2@opbms.com` | Dept + project management, approvals |
| **Project Manager** | Paul ProjMgr | `proj@opbms.com` | Project management, expense approvals |
| **Project Manager 2** | Priya ProjMgr2 | `proj2@opbms.com` | Project management, expense approvals |
| **Employee** | Emma Employee | `employee@opbms.com` | Submit expenses, view own records |
| **Employee 2** | Sam Employee2 | `employee2@opbms.com` | Submit expenses, view own records |
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
- Department and project management
- Budget creation and approval workflow
- Expense approval queue (review, approve, reject)
- Income recording
- Financial reports

### Project Manager
- Project-level management and budget creation
- Expense submission and approval for own projects
- Financial reports

### Employee
- Submit and track own expenses
- View personal expense history
- Personal finance module (transactions, budgets, reports)

### Personal User
- Personal finance dashboard (income/expense summary)
- Transaction management (add income & expense entries)
- Monthly budget limits by category
- Personal reports with charts (income vs expense, category breakdown, budget vs actual)

---

## Database Schema

The database uses **11 tables** and **2 views**:

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
| `v_project_budget_summary` | Budget vs actual spending per project with utilization % |
| `v_dept_budget_summary` | Budget vs actual spending per department with utilization % |

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
- `expenses.status` ENUM controls the approval workflow: `pending → approved / rejected`
- `budgets.entity_type` ENUM (`organization`, `department`, `project`) allows a single table to serve all budget levels
- `approval_history` provides a complete audit trail separate from the `expenses` table
- `personal_transactions` and `personal_budgets` are entirely user-scoped (no cross-user access)
- All foreign keys use `ON DELETE CASCADE` or `ON DELETE RESTRICT` as appropriate

---

## Known Limitations

1. **No file upload for receipts** — The `expenses.receipt_path` column exists in the schema but the UI does not implement file upload. This can be added with PHP's `move_uploaded_file()`.
2. **No email notifications** — Password resets and approval notifications are in-app only; no SMTP integration.
3. **No pagination on large tables** — All table listings fetch all records. For production use, add `LIMIT/OFFSET` pagination.
4. **Single organization per deployment** — While the schema supports multiple organizations, role-based filtering for multi-org isolation is simplified (admins see all data).
5. **Password security** — Demo seeds use a common password. In production, enforce strong password policies and use `password_hash()` with `PASSWORD_BCRYPT` (already implemented in the application).
6. **Not tested under HTTPS** — The application uses `session_start()` and cookie defaults. For production, enable `session.cookie_secure = 1` and serve over HTTPS.

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
