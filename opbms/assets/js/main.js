// OPBMS - Main JavaScript
document.addEventListener('DOMContentLoaded', function () {

  // Sidebar toggle
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
    // Close on outside click (mobile)
    document.addEventListener('click', function (e) {
      if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

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
