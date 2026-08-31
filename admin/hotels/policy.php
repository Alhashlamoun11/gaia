<?php
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$hotelId = (int)($_GET['id'] ?? 0);
if ($hotelId <= 0) {
    http_response_code(404);
    echo 'Hotel not found';
    exit;
}

// Permission check: super admin or manager linked via hotels_users
function userCanManageHotel(int $hotelId): bool {
    $user = admin_user();
    if (!$user) return false;
    if (is_super_admin()) return true;
    $stmt = getPDO()->prepare('SELECT 1 FROM hotels_users WHERE hotel_id = ? AND user_id = ?');
    $stmt->execute([$hotelId, $user['id']]);
    return (bool)$stmt->fetchColumn();
}

if (!userCanManageHotel($hotelId)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Fetch existing policy or create empty
$stmt = $pdo->prepare('SELECT policy FROM cancellation_policies WHERE hotel_id = ?');
$stmt->execute([$hotelId]);
$policy = $stmt->fetchColumn();
if ($policy === false) $policy = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $newPolicy = $_POST['policy'] ?? '';
    // Upsert policy
    $stmt = $pdo->prepare('INSERT INTO cancellation_policies (hotel_id, policy) VALUES (?, ?) ON DUPLICATE KEY UPDATE policy = VALUES(policy)');
    $stmt->execute([$hotelId, $newPolicy]);
    admin_flash('Policy saved.', 'success');
    header('Location: ' . admin_url('hotels/policy.php?id=' . $hotelId));
    exit;
}

$admin_page_title = t('admin.cancellation_policy','Cancellation Policy') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.cancellation_policy','Cancellation Policy');
$admin_active = 'hotels';
$C = fn($v) => htmlspecialchars((string)($v ?? ''));
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
          <h3><?= $C(t('admin.cancellation_policy')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <?php require __DIR__ . '/../components/policy-form.php'; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>
