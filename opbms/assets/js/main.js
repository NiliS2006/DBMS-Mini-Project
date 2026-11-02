// OPBMS - Main JavaScript
document.addEventListener('DOMContentLoaded', function () {

  // Sidebar toggle
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      const isOpen = sidebar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
    // Close on outside click (mobile)
    document.addEventListener('click', function (e) {
      if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Landing-page navigation
  const siteMenuToggle = document.getElementById('siteMenuToggle');
  const siteNavigation = document.getElementById('siteNavigation');
  if (siteMenuToggle && siteNavigation) {
    siteMenuToggle.addEventListener('click', function () {
      const isOpen = siteNavigation.classList.toggle('open');
      siteMenuToggle.setAttribute('aria-expanded', String(isOpen));
      siteMenuToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
    });
    siteNavigation.querySelectorAll('a[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function () {
        siteNavigation.classList.remove('open');
        siteMenuToggle.setAttribute('aria-expanded', 'false');
        siteMenuToggle.setAttribute('aria-label', 'Open navigation');
      });
    });
  }

  // Search and filter the recent records shown on dashboards
  document.querySelectorAll('table[id]').forEach(function (table) {
    const search = document.querySelector('[data-filter-search="' + table.id + '"]');
    const status = document.querySelector('[data-filter-status="' + table.id + '"]');
    if (!search && !status) return;

    const rows = Array.from(table.querySelectorAll('tbody tr[data-filter-item]'));
    const emptyRow = table.querySelector('[data-filter-empty]');
    function applyFilters() {
      const query = search ? search.value.trim().toLocaleLowerCase() : '';
      const selectedStatus = status ? status.value.toLocaleLowerCase() : '';
      let visibleCount = 0;
      rows.forEach(function (row) {
        const matchesSearch = row.textContent.toLocaleLowerCase().includes(query);
        const badge = row.querySelector('.badge');
        const matchesStatus = !selectedStatus || (badge && badge.textContent.trim().toLocaleLowerCase() === selectedStatus);
        row.hidden = !(matchesSearch && matchesStatus);
        if (!row.hidden) visibleCount++;
      });
      if (emptyRow) emptyRow.hidden = visibleCount > 0;
    }

    if (search) search.addEventListener('input', applyFilters);
    if (status) status.addEventListener('change', applyFilters);
  });

  // Auto-dismiss alerts after 5s
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 0.5s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 500);
    }, 5000);
  });

  // Confirm destructive actions
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // Dynamic budget parent selector
  const entityType = document.getElementById('entity_type');
  const entityIdWrap = document.getElementById('entity_id_wrap');
  if (entityType && entityIdWrap) {
    entityType.addEventListener('change', loadEntityOptions);
    loadEntityOptions();
    function loadEntityOptions() {
      const type = entityType.value;
      if (!type) { entityIdWrap.innerHTML = '<select name="entity_id" class="form-control" required><option value="">-- Select type first --</option></select>'; return; }
      fetch('/opbms/ajax/get_entities.php?type=' + type)
        .then(r => r.json())
        .then(data => {
          let opts = '<option value="">-- Select --</option>';
          data.forEach(function (d) { opts += '<option value="' + d.id + '">' + d.name + '</option>'; });
          entityIdWrap.innerHTML = '<select name="entity_id" id="entity_id" required>' + opts + '</select>';
        });
    }
  }

  // Project filter on expense form
  const deptSelect = document.getElementById('department_id');
  const projSelect = document.getElementById('project_id');
  if (deptSelect && projSelect) {
    deptSelect.addEventListener('change', function () {
      const deptId = this.value;
      fetch('/opbms/ajax/get_projects.php?dept_id=' + deptId)
        .then(r => r.json())
        .then(data => {
          let opts = '<option value="">-- None --</option>';
          data.forEach(function (p) { opts += '<option value="' + p.id + '">' + p.name + '</option>'; });
          projSelect.innerHTML = opts;
        });
    });
  }

  // Client-side amount validation (display only; server also validates)
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const amtField = form.querySelector('input[name="amount"]');
      if (amtField && parseFloat(amtField.value) <= 0) {
        e.preventDefault();
        alert('Amount must be greater than 0.');
        amtField.focus();
      }
    });
  });
});
