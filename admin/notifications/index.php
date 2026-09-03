<?php
/**
 * admin/notifications/index.php
 * Hotel Broadcast Notifications Hub
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$adminMe = admin_user();

if (($adminMe['role_slug'] ?? '') !== 'super_admin') {
    admin_flash(t('admin.access_denied', 'Access Denied. Only Super Administrators can manage hotel broadcasts.'), 'error');
    header('Location: ' . admin_url('index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'delete') {
        $pdo->prepare("DELETE FROM hotel_notifications WHERE id = ?")->execute([$id]);
        admin_flash('Notification record deleted.', 'success');
    }
    header('Location: ' . admin_url('notifications/index.php'));
    exit;
}

$pg = AdminQuery::pagination(20);
$q = AdminQuery::search();

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(hn.title LIKE :q OR hn.message LIKE :q OR h.name LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$wh = implode(' AND ', $where);

$c = $pdo->prepare("
    SELECT COUNT(*) 
    FROM hotel_notifications hn
    LEFT JOIN hotels h ON h.id = hn.hotel_id
    WHERE $wh
");
$c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page'] = $pages; $pg['offset'] = ($pg['page'] - 1) * $pg['per_page']; }

$stmt = $pdo->prepare("
    SELECT hn.*, h.name AS hotel_name, h.city AS hotel_city
    FROM hotel_notifications hn
    LEFT JOIN hotels h ON h.id = hn.hotel_id
    WHERE $wh
    ORDER BY hn.id DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$url_builder = fn($ov = []) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];

$admin_page_title = 'Hotel Broadcasts & Notifications — GAIA Admin';
$admin_topbar_title = 'Hotel Notifications';
$admin_active = 'notifications';
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <div class="card-head">
          <div>
            <h3><i class="fa-solid fa-bell"></i> Hotel Broadcasts &amp; Alerts</h3>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">Send operational announcements, policy updates, and alerts to hotel partners.</p>
          </div>
          <a class="btn btn-sm btn-primary" href="compose.php"><i class="fa-solid fa-paper-plane"></i> Send Broadcast / Alert</a>
        </div>

        <form method="get" action="index.php" class="toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search notifications or hotels...">
          </div>
          <button type="submit" class="btn btn-sm">Apply</button>
          <a href="index.php" class="btn btn-ghost btn-sm">Reset</a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Target Hotel</th>
                <th>Type</th>
                <th>Notification Title</th>
                <th>Message Content</th>
                <th>Read Status</th>
                <th>Sent At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$notifications): ?>
                <tr><td colspan="7"><div class="muted" style="text-align:center;padding:24px;">No hotel notifications found. Click "Send Broadcast / Alert" above to create one.</div></td></tr>
              <?php else: foreach ($notifications as $n): ?>
                <tr>
                  <td>
                    <?php if (!empty($n['hotel_name'])): ?>
                      <strong style="color:var(--navy);"><i class="fa-solid fa-hotel" style="color:var(--teal); margin-right:4px;"></i> <?= htmlspecialchars($n['hotel_name']) ?></strong>
                      <?php if (!empty($n['hotel_city'])): ?>
                        <div class="muted" style="font-size:11.5px;"><?= htmlspecialchars($n['hotel_city']) ?></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700;"><i class="fa-solid fa-bullhorn"></i> Global Broadcast (All)</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                      $type = $n['type'] ?? 'info';
                      $typeColors = [
                        'info' => ['bg' => '#e0f2fe', 'color' => '#0369a1', 'icon' => 'fa-info-circle'],
                        'success' => ['bg' => '#dcfce7', 'color' => '#15803d', 'icon' => 'fa-check-circle'],
                        'warning' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-triangle-exclamation'],
                        'alert' => ['bg' => '#fee2e2', 'color' => '#b91c1c', 'icon' => 'fa-circle-exclamation'],
                      ];
                      $style = $typeColors[$type] ?? $typeColors['info'];
                    ?>
                    <span class="badge" style="background:<?= $style['bg'] ?>; color:<?= $style['color'] ?>; font-weight:700;">
                      <i class="fa-solid <?= $style['icon'] ?>"></i> <?= ucfirst($type) ?>
                    </span>
                  </td>
                  <td><strong style="color:var(--ink); font-size:13.5px;"><?= htmlspecialchars($n['title']) ?></strong></td>
                  <td>
                    <div style="font-size:13px; color:var(--ink); max-width:320px; line-height:1.4;">
                      <?= htmlspecialchars($n['message']) ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-<?= (int)$n['is_read'] ? 'active' : 'inactive' ?>">
                      <?= (int)$n['is_read'] ? 'Read' : 'Unread' ?>
                    </span>
                  </td>
                  <td style="font-size:12px; color:var(--muted); white-space:nowrap;"><?= htmlspecialchars(substr($n['created_at'] ?? '', 0, 16)) ?></td>
                  <td>
                    <div class="actions">
                      <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button class="icon-btn danger" type="submit" onclick="return confirm('Delete this notification record?')" title="Delete"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php $page = $pg['page']; $pages = $pages; $total = $total; $offset = $pg['offset']; $per_page = $pg['per_page']; $url_builder = $url_builder; require __DIR__ . '/../components/pagination.php'; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>
