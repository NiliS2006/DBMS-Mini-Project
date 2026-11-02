-- Apply only to an existing OPBMS database created from the previous schema.
-- Do not run after importing the updated opbms.sql; that schema already has these keys.
-- Back up the database first. Resolve any nonzero validation results before applying.

SELECT COUNT(*) AS invalid_expense_hierarchies
FROM expenses e
LEFT JOIN departments d ON d.id=e.department_id
LEFT JOIN projects p ON p.id=e.project_id
WHERE (e.department_id IS NOT NULL AND (d.id IS NULL OR e.organization_id IS NULL OR d.organization_id<>e.organization_id))
   OR (e.project_id IS NOT NULL AND (p.id IS NULL OR e.department_id IS NULL OR p.department_id<>e.department_id));

SELECT COUNT(*) AS invalid_income_hierarchies
FROM income_records i
LEFT JOIN departments d ON d.id=i.department_id
LEFT JOIN projects p ON p.id=i.project_id
WHERE (i.department_id IS NOT NULL AND (d.id IS NULL OR i.organization_id IS NULL OR d.organization_id<>i.organization_id))
   OR (i.project_id IS NOT NULL AND (p.id IS NULL OR i.department_id IS NULL OR p.department_id<>i.department_id));

ALTER TABLE departments
    ADD UNIQUE KEY uq_dept_id_org (id, organization_id);

ALTER TABLE projects
    ADD UNIQUE KEY uq_project_id_dept (id, department_id);

ALTER TABLE expenses
    ADD CONSTRAINT fk_expenses_department_org
        FOREIGN KEY (department_id, organization_id)
        REFERENCES departments (id, organization_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_expenses_project_department
        FOREIGN KEY (project_id, department_id)
        REFERENCES projects (id, department_id) ON DELETE CASCADE;

ALTER TABLE income_records
    ADD CONSTRAINT fk_income_department_org
        FOREIGN KEY (department_id, organization_id)
        REFERENCES departments (id, organization_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_income_project_department
        FOREIGN KEY (project_id, department_id)
        REFERENCES projects (id, department_id) ON DELETE CASCADE;
