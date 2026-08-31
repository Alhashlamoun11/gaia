<?php
/**
 * admin/partners/applications.php
 * ------------------------------------------------------------
 * Manage Hotel Partner Applications.
 * STRICTLY requires super_admin.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_super_admin();

$pdo = getPDO();

// ---- Query params ----
$pg   = AdminQuery::pagination(15);
$q    = AdminQuery::search();
$s    = AdminQuery::sort(['id','reference_code','full_name','hotel_name','status','created_at'], 'created_at');
$st   = AdminQuery::statusFilter(['pending','approved','rejected','cancelled']);

$where  = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = "(reference_code LIKE :q OR full_name LIKE :q OR hotel_name LIKE :q OR email LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($st !== '') {
    $where[] = 'status = :st';
    $params[':st'] = $st;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM partner_applications WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) {
    $pg['page'] = $pages;
    $pg['offset'] = ($pg['page'] - 1) * $pg['per_page'];
}

$sql = "SELECT id, reference_code, full_name, email, hotel_name, status, created_at
        FROM partner_applications WHERE $whereSql
        ORDER BY {$s['col']} {$s['dir']}
        LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = function (array $ov = []) {
    return '?' . http_build_query(array_merge($_GET, $ov));
};

$account_alerts     = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title   = t('admin.partners.applications', 'Partner Applications') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.partners.applications', 'Partner Applications');
$admin_active       = 'applications';
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
          <h3><i class="fa-solid fa-file-signature"></i> <?= htmlspecialchars(t('admin.partners.applications', 'Partner Applications')) ?></h3>
          <span class="muted"><?= (int)$total ?> <?= htmlspecialchars(t('admin.total_records', 'Records')) ?></span>
        </div>

        <form method="get" action="applications.php" class="toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('admin.search')) ?>...">
          </div>
          <select name="status">
            <option value=""><?= htmlspecialchars(t('admin.all_statuses', 'All Statuses')) ?></option>
            <option value="pending" <?= $st === 'pending' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.status_pending', 'Pending')) ?></option>
            <option value="approved" <?= $st === 'approved' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.status_approved', 'Approved')) ?></option>
            <option value="rejected" <?= $st === 'rejected' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.status_rejected', 'Rejected')) ?></option>
            <option value="cancelled" <?= $st === 'cancelled' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.status_cancelled', 'Cancelled')) ?></option>
          </select>
          <button type="submit" class="btn btn-sm"><?= htmlspecialchars(t('admin.apply', 'Apply')) ?></button>
          <a href="applications.php" class="btn btn-ghost btn-sm"><?= htmlspecialchars(t('admin.reset', 'Reset')) ?></a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th><?= AdminQuery::sortLink('reference_code', t('admin.reference', 'Reference'), ['id','reference_code','full_name','hotel_name','status','created_at'], 'created_at') ?></th>
                <th><?= AdminQuery::sortLink('full_name', t('admin.applicant', 'Applicant'), ['id','reference_code','full_name','hotel_name','status','created_at'], 'created_at') ?></th>
                <th><?= AdminQuery::sortLink('hotel_name', t('admin.hotel', 'Hotel Name'), ['id','reference_code','full_name','hotel_name','status','created_at'], 'created_at') ?></th>
                <th><?= htmlspecialchars(t('admin.email')) ?></th>
                <th><?= AdminQuery::sortLink('status', t('admin.status'), ['id','reference_code','full_name','hotel_name','status','created_at'], 'created_at') ?></th>
                <th><?= AdminQuery::sortLink('created_at', t('admin.submitted_date', 'Submitted'), ['id','reference_code','full_name','hotel_name','status','created_at'], 'created_at') ?></th>
                <th><?= htmlspecialchars(t('admin.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="7"><div class="muted" style="text-align:center;padding:20px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td class="code"><?= htmlspecialchars($r['reference_code']) ?></td>
                  <td class="code"><?= htmlspecialchars($r['full_name']) ?></td>
                  <td><?= htmlspecialchars($r['hotel_name']) ?></td>
                  <td><?= htmlspecialchars($r['email']) ?></td>
                  <td>
                    <?php if ($r['status'] === 'pending'): ?>
                        <span class="badge badge-warning"><?= htmlspecialchars($r['status']) ?></span>
                    <?php elseif ($r['status'] === 'approved'): ?>
                        <span class="badge badge-success"><?= htmlspecialchars($r['status']) ?></span>
                    <?php elseif ($r['status'] === 'rejected'): ?>
                        <span class="badge badge-error"><?= htmlspecialchars($r['status']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-default"><?= htmlspecialchars($r['status']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$r['created_at']))) ?></td>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="application-view.php?id=<?= (int)$r['id'] ?>" title="<?= htmlspecialchars(t('admin.view')) ?>"><i class="fa-solid fa-eye"></i></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php
          $page = $pg['page']; $pages = $pages; $total = $total; $offset = $pg['offset']; $per_page = $pg['per_page']; $url_builder = $url_builder;
          require __DIR__ . '/../components/pagination.php';
        ?>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>
