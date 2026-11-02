-- Apply only to an existing OPBMS database created from the previous schema.
-- Do not run after importing the updated opbms.sql; it already includes cancellation.
-- Back up the database before applying.

ALTER TABLE expenses
    MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';

ALTER TABLE approval_history
    MODIFY action ENUM('submitted','approved','rejected','resubmitted','cancelled') NOT NULL;
