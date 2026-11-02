-- Apply only to an existing OPBMS database created from the previous schema.
-- Do not run after importing the updated opbms.sql; it already includes these views.

CREATE OR REPLACE VIEW v_project_budget_summary AS
SELECT
    p.id AS project_id,
    p.name AS project_name,
    p.status,
    d.name AS department_name,
    o.name AS org_name,
    o.id AS organization_id,
    y.fiscal_year,
    COALESCE(b.amount,0) AS allocated_budget,
    COALESCE(e.approved_expenditure,0) AS approved_expenditure,
    COALESCE(b.amount,0)-COALESCE(e.approved_expenditure,0) AS remaining_budget,
    CASE WHEN COALESCE(b.amount,0)>0
         THEN ROUND(COALESCE(e.approved_expenditure,0)/b.amount*100,2)
         ELSE 0 END AS utilization_pct,
    COALESCE(e.pending_amount,0) AS pending_amount
FROM projects p
JOIN departments d ON d.id=p.department_id
JOIN organizations o ON o.id=d.organization_id
JOIN (
    SELECT entity_id AS project_id, fiscal_year FROM budgets WHERE entity_type='project' AND status='approved'
    UNION
    SELECT project_id, YEAR(expense_date) FROM expenses WHERE project_id IS NOT NULL
    UNION
    SELECT id, YEAR(CURDATE()) FROM projects
) y ON y.project_id=p.id
LEFT JOIN (
    SELECT entity_id AS project_id, fiscal_year, SUM(amount) AS amount
    FROM budgets WHERE entity_type='project' AND status='approved'
    GROUP BY entity_id, fiscal_year
) b ON b.project_id=p.id AND b.fiscal_year=y.fiscal_year
LEFT JOIN (
    SELECT project_id, YEAR(expense_date) AS fiscal_year,
           SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) AS approved_expenditure,
           SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) AS pending_amount
    FROM expenses WHERE project_id IS NOT NULL
    GROUP BY project_id, YEAR(expense_date)
) e ON e.project_id=p.id AND e.fiscal_year=y.fiscal_year;

CREATE OR REPLACE VIEW v_dept_budget_summary AS
SELECT
    d.id AS dept_id,
    d.name AS dept_name,
    o.name AS org_name,
    o.id AS organization_id,
    y.fiscal_year,
    COALESCE(b.amount,0) AS allocated_budget,
    COALESCE(e.approved_expenditure,0) AS approved_expenditure,
    COALESCE(b.amount,0)-COALESCE(e.approved_expenditure,0) AS remaining_budget,
    CASE WHEN COALESCE(b.amount,0)>0
         THEN ROUND(COALESCE(e.approved_expenditure,0)/b.amount*100,2)
         ELSE 0 END AS utilization_pct
FROM departments d
JOIN organizations o ON o.id=d.organization_id
JOIN (
    SELECT entity_id AS dept_id, fiscal_year FROM budgets WHERE entity_type='department' AND status='approved'
    UNION
    SELECT department_id, YEAR(expense_date) FROM expenses WHERE department_id IS NOT NULL
    UNION
    SELECT id, YEAR(CURDATE()) FROM departments
) y ON y.dept_id=d.id
LEFT JOIN (
    SELECT entity_id AS dept_id, fiscal_year, SUM(amount) AS amount
    FROM budgets WHERE entity_type='department' AND status='approved'
    GROUP BY entity_id, fiscal_year
) b ON b.dept_id=d.id AND b.fiscal_year=y.fiscal_year
LEFT JOIN (
    SELECT department_id, YEAR(expense_date) AS fiscal_year,
           SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) AS approved_expenditure
    FROM expenses WHERE department_id IS NOT NULL
    GROUP BY department_id, YEAR(expense_date)
) e ON e.department_id=d.id AND e.fiscal_year=y.fiscal_year;
