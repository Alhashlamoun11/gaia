<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('adminSidebarToggle');
  const sidebar = document.getElementById('adminSidebar');
  if(toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }
  // Lazy load
  document.querySelectorAll('img.lazy').forEach(img => {
    if(img.dataset.src) img.src = img.dataset.src;
  });
});
</script>
</body>
</html>