<?php
/**
 * admin/components/_layout.php
 * ------------------------------------------------------------
 * Convenience layout composer. Expects bootstrap already loaded.
 * Emits: header, sidebar, topbar, main open, alerts, then the
 * content via $content (a closure or string), then footer.
 *
 * Expected variables:
 *   $admin_page_title  : <title>
 *   $admin_topbar_title: topbar heading
 *   $admin_active      : sidebar highlight key
 *   $admin_breadcrumbs : array of ['label'=>, 'url'=>] (optional)
 *   $content           : string of HTML to render in main
 * ------------------------------------------------------------
 */
?>
<?php require __DIR__ . '/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/topbar.php'; ?>
    <div class="admin-content">
      <?php if (!empty($admin_breadcrumbs)): ?>
        <?php
          $crumbs = $admin_breadcrumbs;
          require __DIR__ . '/breadcrumb.php';
        ?>
      <?php endif; ?>
      <?php
        $admin_alert = admin_flash_peek();
        if ($admin_alert):
          $alertType = in_array($admin_alert['type'], ['success', 'error', 'info'], true) ? $admin_alert['type'] : 'success';
      ?>
        <div class="alert alert-<?= htmlspecialchars($alertType) ?>"><?= htmlspecialchars($admin_alert['msg']) ?></div>
      <?php endif; ?>
      <?= $content ?? '' ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
