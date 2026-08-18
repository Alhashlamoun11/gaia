/* ============================================================
   GAIA ADMIN PANEL — Scripts
   Sidebar toggle, modal-delete wiring, lazy images, confirm,
   upload image preview, auto-hide alerts.
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // ---- Sidebar toggle (mobile) ----
    var toggle = document.getElementById('adminMenuToggle');
    var sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
      toggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
      });
    }

    // ---- Modal delete wiring ----
    // Buttons with attributes data-delete-url + data-delete-message open a
    // confirm modal; the confirm button navigates to the URL.
    var mask = document.getElementById('deleteModal');
    var confirmBtn = document.getElementById('deleteConfirm');
    var cancelBtn = document.getElementById('deleteCancel');

    function openModal(message, url) {
      if (!mask) return;
      var msgEl = document.getElementById('deleteModalMsg');
      if (msgEl) msgEl.textContent = message || 'Are you sure you want to delete this record?';
      mask.classList.add('open');
      if (confirmBtn) confirmBtn.setAttribute('data-url', url || '');
    }
    function closeModal() {
      if (mask) mask.classList.remove('open');
    }
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (mask) mask.addEventListener('click', function (e) {
      if (e.target === mask) closeModal();
    });
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        var url = confirmBtn.getAttribute('data-url') || '';
        if (url) window.location.href = url;
      });
    }
    document.querySelectorAll('[data-delete]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openModal(btn.getAttribute('data-delete-message'), btn.getAttribute('data-delete'));
      });
    });

    // ---- Generic confirm for form submit links ----
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (!window.confirm(btn.getAttribute('data-confirm'))) {
          e.preventDefault();
        }
      });
    });

    // ---- Lazy image loading ----
    var lazy = document.querySelectorAll('img.lazy');
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            var img = entry.target;
            img.src = img.getAttribute('data-src');
            img.addEventListener('load', function () { img.classList.add('loaded'); });
            io.unobserve(img);
          }
        });
      });
      lazy.forEach(function (img) { io.observe(img); });
    } else {
      lazy.forEach(function (img) {
        img.src = img.getAttribute('data-src');
        img.classList.add('loaded');
      });
    }

    // ---- Image upload preview ----
    document.querySelectorAll('[data-preview-target]').forEach(function (input) {
      input.addEventListener('change', function () {
        var target = document.querySelector(input.getAttribute('data-preview-target'));
        if (!target || !input.files || !input.files[0]) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          target.src = e.target.result;
          target.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
      });
    });

    // ---- Auto-hide flash alerts ----
    setTimeout(function () {
      document.querySelectorAll('.alert').forEach(function (a) {
        a.style.transition = 'opacity .4s';
        a.style.opacity = '0';
        setTimeout(function () { a.remove(); }, 400);
      });
    }, 5000);
  });
})();
