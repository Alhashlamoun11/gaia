<div class="admin-sidebar-backdrop" id="adminSidebarBackdrop"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('adminSidebarToggle');
  const sidebar = document.getElementById('adminSidebar');
  const closeBtn = document.getElementById('adminSidebarClose');
  const backdrop = document.getElementById('adminSidebarBackdrop');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (backdrop) backdrop.classList.add('active');
    document.body.style.overflow = window.innerWidth <= 860 ? 'hidden' : '';
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (toggle) {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      if (sidebar && sidebar.classList.contains('open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeSidebar);
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });

  // Close sidebar on link click in mobile view
  if (sidebar) {
    sidebar.querySelectorAll('.admin-sidebar-nav a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 860) {
          closeSidebar();
        }
      });
    });
  }

  window.addEventListener('resize', () => {
    if (window.innerWidth > 860) {
      closeSidebar();
    }
  });

  // Lazy load images
  document.querySelectorAll('img.lazy').forEach(img => {
    if (img.dataset.src) img.src = img.dataset.src;
  });
});
</script>
</body>
</html>