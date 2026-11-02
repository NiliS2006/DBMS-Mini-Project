# OPBMS Website and Collaboration Guide

This guide describes the public website and frontend conventions so collaborators can extend the existing OPBMS application without replacing its PHP/MySQL backend.

## Local setup

1. Follow the XAMPP and database import steps in the repository [README](../../README.md).
2. Put the `opbms` directory under the Apache document root as `htdocs/opbms`. The application uses `/opbms` as its base URL.
3. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` in `config/database.php` for the MySQL instance you intend to use.
4. Open `http://localhost/opbms/`. PHP 8+ and MySQL are required for database-backed pages and account flows.

The public landing page and login/sign-up forms can render without a database connection. Creating an account, logging in, and viewing dashboards require a reachable database with the existing OPBMS schema.

## Public website

The public landing page lives in `index.php` and includes:

- Responsive navigation with Home, Features, How it works, About, Contact, Log in, and Get started links.
- A dashboard preview illustration. Its example amounts and activity are **illustrative only**; they are not production data.
- Product features and the Organization → Department → Project → Budget → Expense hierarchy.
- An about section and a contact form.

`assets/css/style.css` contains both the existing app styles and the public-site styles. Shared color tokens are defined at the top of that stylesheet. `assets/js/main.js` handles the mobile navigation and dashboard search/filter controls, in addition to the existing application behavior.

## Authentication and dashboards

- `auth/login.php` uses the existing `users` table, password hashes, sessions, and role-based dashboard routing.
- `auth/register.php` creates **personal-role accounts only**. Organization roles must be assigned by an administrator; do not allow public role selection.
- `auth/logout.php` clears the existing session and returns the user to the public home page.
- `dashboard.php` and `personal/dashboard.php` continue to calculate financial totals and charts from MySQL queries. Do not replace these with preview/demo values.
- Existing access controls, CSRF helpers, and data-scope helpers live in `includes/auth.php` and `includes/functions.php`; reuse them when adding protected pages or forms.

No separate database, JavaScript application framework, package manager, or test framework is introduced by the website.

## Contact email setup

The contact form submits to `contact.php`. It validates the fields and CSRF token before attempting email delivery. To enable delivery:

1. Set the PHP/Apache server environment variable `OPBMS_CONTACT_EMAIL` to the intended recipient address.
2. Configure PHP `mail()` for your local or hosted mail transport.
3. Restart Apache and submit a test message.

Do not commit recipient credentials, SMTP passwords, API keys, or other secrets. If the recipient or mail transport is not configured, the handler reports that email delivery is unavailable rather than showing a success message.

## Collaborating safely

- Keep changes in the existing `opbms` PHP application; avoid creating another database or authentication system.
- Preserve the database relationships and role-specific access rules. Make schema changes through a new numbered migration and document whether it is for fresh installs, existing databases, or both.
- Do not commit local database dumps containing real data, receipt uploads, credentials, or environment-specific secrets.
- Test new role-protected pages with the relevant roles. Use disposable data for create/edit/approval workflows.
- Check PHP syntax with `php -l`, JavaScript syntax with `node --check opbms/assets/js/main.js`, and test the site in desktop and mobile widths.
- Test database-dependent flows against a local MySQL instance configured with the credentials and port in `config/database.php`.

The contact form depends on a configured mail transport, and full authentication/dashboard checks depend on MySQL being available; these are not covered by a standalone static preview.
