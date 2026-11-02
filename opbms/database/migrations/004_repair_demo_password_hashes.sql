-- Apply only to an existing OPBMS database created from the previous demo seed.
-- Do not run after importing the updated opbms.sql.
-- Existing password hashes produced by PHP password_hash() are left unchanged.

UPDATE users
SET password_hash='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE email IN (
    'admin@opbms.com',
    'dept@opbms.com',
    'proj@opbms.com',
    'employee@opbms.com',
    'employee2@opbms.com',
    'personal@opbms.com',
    'dept2@opbms.com',
    'proj2@opbms.com'
)
AND password_hash NOT LIKE '$2y$%'
AND password_hash NOT LIKE '$argon2%';
