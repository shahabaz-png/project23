/* app.js - small UI helpers (the server still enforces all rules) */
document.addEventListener('DOMContentLoaded', function () {
  // Mobile sidebar toggle
  var menuBtn = document.getElementById('menuBtn');
  var sidebar = document.getElementById('sidebar');
  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', function () { sidebar.classList.toggle('open'); });
  }

  // Confirmation dialog for forms with data-confirm="..."
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.getAttribute('data-confirm'))) { ev.preventDefault(); }
    });
  });

  // Friendly client-side file checks (server validates again)
  document.querySelectorAll('input[type=file][data-max]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files[0];
      if (!file) { return; }
      var ext = file.name.split('.').pop().toLowerCase();
      if (['pdf', 'docx', 'zip'].indexOf(ext) === -1) {
        alert('Only PDF, DOCX and ZIP files are allowed.'); input.value = '';
      } else if (file.size > parseInt(input.getAttribute('data-max'), 10)) {
        alert('File is too large. Maximum size is ' + (input.getAttribute('data-max') / 1048576) + ' MB.'); input.value = '';
      }
    });
  });

  // Disable submit buttons after click to avoid double submission (loading state)
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) { return; }
      var btn = form.querySelector('button[type=submit]');
      if (btn) { setTimeout(function () { btn.disabled = true; btn.textContent = 'Please wait...'; }, 0); }
    });
  });

  // Fade out success messages
  document.querySelectorAll('.alert-success').forEach(function (a) {
    setTimeout(function () { a.style.display = 'none'; }, 12000);
  });
});
