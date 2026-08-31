<?php
/**
 * admin/partners/application-view.php
 * ------------------------------------------------------------
 * View and process a Hotel Partner Application.
 * STRICTLY requires super_admin.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_super_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM partner_applications WHERE id = ?');
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found', 'Not Found') . ' — GAIA TOURS &amp; TRAVEL';
    $admin_topbar_title = t('admin.not_found', 'Not Found');
    $admin_active       = 'applications';
    require __DIR__ . '/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">Application not found.</div></div></div></div></div>';
    require __DIR__ . '/../components/footer.php';
    exit;
}

$alerts = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        try {
            $pdo->beginTransaction();

            // Lock row
            $stmt = $pdo->prepare("SELECT status FROM partner_applications WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus !== 'pending' && $currentStatus !== 'under_review') {
                throw new Exception(t('admin.app_not_pending', 'Application is no longer pending.'));
            }

            // 1. Create User
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'hotel_manager' LIMIT 1");
            $stmt->execute();
            $roleId = $stmt->fetchColumn();
            
            if (!$roleId) {
                throw new Exception('Role hotel_manager not found.');
            }

            $parts = explode(' ', $app['full_name'], 2);
            $first = $parts[0];
            $last = $parts[1] ?? '';

            $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password_hash, role_id, status, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, "active", NOW())');
            $stmt->execute([$first, $last, $app['email'], $app['phone'], $app['password_hash'], $roleId]);
            $userId = $pdo->lastInsertId();

            // 2. Create Hotel
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $app['hotel_name']));
            $slug = preg_replace('/-+/', '-', trim($slug, '-'));
            $slugCheck = $pdo->prepare('SELECT COUNT(*) FROM hotels WHERE slug = ?');
            $slugCheck->execute([$slug]);
            if ((int)$slugCheck->fetchColumn() > 0) {
                $slug .= '-' . $id;
            }

            $location = trim(($app['address'] ?? '') . ', ' . ($app['city'] ?? '') . ', ' . ($app['country'] ?? ''), ', ');

            $stmt = $pdo->prepare('INSERT INTO hotels (name, slug, description, location, email, phone, website, check_in_time, check_out_time, cancellation_policy, is_active, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
            $stmt->execute([
                $app['hotel_name'],
                $slug,
                $app['description'],
                $location,
                $app['hotel_email'] ?: $app['email'],
                $app['hotel_phone'] ?: $app['phone'],
                $app['website'],
                $app['check_in_time'],
                $app['check_out_time'],
                $app['cancellation_policy'],
                $app['main_image']
            ]);
            $hotelId = $pdo->lastInsertId();


            // 3. Link Hotel and User
            $stmt = $pdo->prepare('INSERT INTO hotels_users (hotel_id, user_id) VALUES (?, ?)');
            $stmt->execute([$hotelId, $userId]);

            // 4. Update Application
            $stmt = $pdo->prepare('UPDATE partner_applications SET status = "approved", reviewed_by = ?, reviewed_at = NOW(), user_id = ?, hotel_id = ? WHERE id = ?');
            $stmt->execute([$_SESSION['auth_user_id'], $userId, $hotelId, $id]);

            // 5. Audit
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, application_id, action) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['auth_user_id'], $id, 'partner_application_approved']);

            $pdo->commit();
            admin_flash(t('admin.app_approved', 'Application approved. Hotel and Manager account created successfully.'), 'success');
            header("Location: application-view.php?id=$id");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($reason === '') {
            $errors[] = t('admin.rejection_reason_required', 'Rejection reason is required.');
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT status FROM partner_applications WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $currentStatus = $stmt->fetchColumn();

                if ($currentStatus !== 'pending' && $currentStatus !== 'under_review') {
                    throw new Exception(t('admin.app_not_pending', 'Application is no longer pending.'));
                }

                $stmt = $pdo->prepare('UPDATE partner_applications SET status = "rejected", reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE id = ?');
                $stmt->execute([$_SESSION['auth_user_id'], $reason, $id]);

                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, application_id, action) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['auth_user_id'], $id, 'partner_application_rejected']);

                $pdo->commit();
                admin_flash(t('admin.app_rejected', 'Application rejected.'), 'success');
                header("Location: application-view.php?id=$id");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }
}

$account_alerts     = array_merge(array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors), admin_flash_peek() ? [admin_flash()] : []);
$admin_page_title   = t('admin.application', 'Application View') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.application', 'Application View');
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
          <h3><i class="fa-solid fa-file-signature"></i> <?= htmlspecialchars(t('admin.application_details', 'Application Details')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="applications.php"><?= htmlspecialchars(t('admin.back', 'Back')) ?></a>
        </div>

        <div class="grid-2">
            <div>
                <h4><?= htmlspecialchars(t('partner.account_info', 'Applicant Information')) ?></h4>
                <table class="data-table">
                    <tr><th><?= htmlspecialchars(t('admin.reference', 'Reference')) ?></th><td><?= htmlspecialchars($app['reference_code']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('auth.first_name', 'Full Name')) ?></th><td><?= htmlspecialchars($app['full_name']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('auth.email', 'Email')) ?></th><td><?= htmlspecialchars($app['email']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('auth.phone', 'Phone')) ?></th><td><?= htmlspecialchars($app['phone']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('admin.submitted_date', 'Submitted')) ?></th><td><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$app['created_at']))) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('admin.status', 'Status')) ?></th>
                        <td>
                            <?php if ($app['status'] === 'pending'): ?>
                                <span class="badge badge-warning"><?= htmlspecialchars($app['status']) ?></span>
                            <?php elseif ($app['status'] === 'approved'): ?>
                                <span class="badge badge-success"><?= htmlspecialchars($app['status']) ?></span>
                            <?php elseif ($app['status'] === 'rejected'): ?>
                                <span class="badge badge-error"><?= htmlspecialchars($app['status']) ?></span>
                            <?php else: ?>
                                <span class="badge badge-default"><?= htmlspecialchars($app['status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php if ($app['status'] === 'approved'): ?>
                    <h4 style="margin-top:20px;"><?= htmlspecialchars(t('admin.result', 'Resulting Records')) ?></h4>
                    <table class="data-table">
                        <tr><th>User ID</th><td><a href="<?= admin_url('users/view.php?id='.$app['user_id']) ?>"><?= (int)$app['user_id'] ?></a></td></tr>
                        <tr><th>Hotel ID</th><td><a href="<?= admin_url('hotels/view.php?id='.$app['hotel_id']) ?>"><?= (int)$app['hotel_id'] ?></a></td></tr>
                        <tr><th><?= htmlspecialchars(t('admin.reviewed_at', 'Reviewed At')) ?></th><td><?= htmlspecialchars($app['reviewed_at']) ?></td></tr>
                    </table>
                <?php endif; ?>
                
                <?php if ($app['status'] === 'rejected'): ?>
                    <h4 style="margin-top:20px;"><?= htmlspecialchars(t('admin.rejection_details', 'Rejection Details')) ?></h4>
                    <table class="data-table">
                        <tr><th><?= htmlspecialchars(t('admin.rejection_reason', 'Reason')) ?></th><td><?= nl2br(htmlspecialchars((string)$app['rejection_reason'])) ?></td></tr>
                        <tr><th><?= htmlspecialchars(t('admin.reviewed_at', 'Reviewed At')) ?></th><td><?= htmlspecialchars($app['reviewed_at']) ?></td></tr>
                    </table>
                <?php endif; ?>
            </div>
            <div>
                <h4><?= htmlspecialchars(t('partner.hotel_info', 'Hotel Information')) ?></h4>
                <table class="data-table">
                    <tr><th><?= htmlspecialchars(t('partner.hotel_name', 'Hotel Name')) ?></th><td><?= htmlspecialchars($app['hotel_name']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.address', 'Address')) ?></th><td><?= htmlspecialchars($app['address']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.city', 'City')) ?></th><td><?= htmlspecialchars($app['city']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.country', 'Country')) ?></th><td><?= htmlspecialchars($app['country']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.hotel_phone', 'Hotel Phone')) ?></th><td><?= htmlspecialchars($app['hotel_phone']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.hotel_email', 'Hotel Email')) ?></th><td><?= htmlspecialchars($app['hotel_email']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.website', 'Website')) ?></th><td><?= htmlspecialchars($app['website']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.check_in_time', 'Check-in')) ?></th><td><?= htmlspecialchars($app['check_in_time']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.check_out_time', 'Check-out')) ?></th><td><?= htmlspecialchars($app['check_out_time']) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.facilities', 'Facilities')) ?></th><td><?= nl2br(htmlspecialchars((string)$app['facilities'])) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.cancellation_policy', 'Cancellation Policy')) ?></th><td><?= nl2br(htmlspecialchars((string)$app['cancellation_policy'])) ?></td></tr>
                    <tr><th><?= htmlspecialchars(t('partner.description', 'Description')) ?></th><td><?= nl2br(htmlspecialchars((string)$app['description'])) ?></td></tr>
                    <?php if ($app['main_image']): ?>
                        <tr><th><?= htmlspecialchars(t('partner.main_image', 'Main Image')) ?></th><td><img src="<?= htmlspecialchars('/' . ltrim($app['main_image'], '/')) ?>" style="max-width:100%; border-radius:8px;"></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php if ($app['status'] === 'pending' || $app['status'] === 'under_review'): ?>
            <div style="margin-top:40px; padding-top:20px; border-top:1px solid var(--line);">
                <form method="post" action="application-view.php?id=<?= (int)$id ?>" style="display:inline-block; margin-right:10px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn" style="background:#137333;" onclick="return confirm('<?= htmlspecialchars(t('admin.confirm_approve', 'Are you sure you want to approve this application? This will create a hotel and manager account.')) ?>');">
                        <i class="fa-solid fa-check"></i> <?= htmlspecialchars(t('admin.approve', 'Approve')) ?>
                    </button>
                </form>

                <form method="post" action="application-view.php?id=<?= (int)$id ?>" style="display:inline-block; vertical-align:top;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <div style="display:flex; gap:10px; align-items:flex-start;">
                        <textarea name="rejection_reason" placeholder="<?= htmlspecialchars(t('admin.rejection_reason', 'Reason for rejection...')) ?>" required rows="2" style="width:300px; padding:10px; border:1px solid var(--line); border-radius:8px;"></textarea>
                        <button type="submit" class="btn btn-error" onclick="return confirm('<?= htmlspecialchars(t('admin.confirm_reject', 'Are you sure you want to reject this application?')) ?>');">
                            <i class="fa-solid fa-xmark"></i> <?= htmlspecialchars(t('admin.reject', 'Reject')) ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>
