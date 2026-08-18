<?php
/**
 * admin/messages/view.php
 * View a single contact message.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch() ?: null;
    if ($row) {
        // Mark as read on view
        $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?" )->execute([$id]);
        $row['is_read'] = 1;
    }
}

if (!$row) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found', 'Not Found') . ' — GAIA TOURS & TRAVEL';
    require __DIR__ . '/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">' . htmlspecialchars(t('admin.not_found_text', 'Not found.')) . '</div><div style="text-align:center;"><a class="btn" href="index.php">' . htmlspecialchars(t('admin.back', 'Back')) . '</a></div></div></div></div></div>';
    require __DIR__ . '/../components/footer.php';
    exit;
}

// Handle reply status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    if ($action === 'replied') {
        $pdo->prepare("UPDATE contact_messages SET replied=1 WHERE id=?")->execute([$id]);
        admin_flash(t('admin.status_updated', 'Status updated.'), 'success');
    } elseif ($action === 'unreplied') {
        $pdo->prepare("UPDATE contact_messages SET replied=0 WHERE id=?")->execute([$id]);
        admin_flash(t('admin.status_updated', 'Status updated.'), 'success');
    }
    header('Location: ' . admin_url('messages/view.php?id=' . (int)$id));
    exit;
}

$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.messages') . ' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.messages');
$admin_active = 'messages';
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
          <h3><i class="fa-solid fa-inbox"></i> <?= $C(t('admin.message_detail', 'Message Detail')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <div class="detail-grid">
          <div><span><?= $C(t('admin.name')) ?></span><strong><?= $C($row['name']) ?></strong></div>
          <div><span><?= $C(t('admin.email')) ?></span><strong><a href="mailto:<?= $C($row['email']) ?>"><?= $C($row['email']) ?></a></strong></div>
          <?php if (!empty($row['phone'])): ?><div><span><?= $C(t('admin.phone')) ?></span><strong><?= $C($row['phone']) ?></strong></div><?php endif; ?>
          <div><span><?= $C(t('admin.created')) ?></span><strong><?= $C(date('Y-m-d H:i', strtotime($row['created_at']))) ?></strong></div>
          <div><span><?= $C(t('admin.subject', 'Subject')) ?></span><strong><?= $C($row['subject'] ?? '—') ?></strong></div>
          <div><span><?= $C(t('admin.status')) ?></span><strong><span class="badge badge-<?= (int)$row['is_read'] ? 'active' : 'inactive' ?>"><?= (int)$row['is_read'] ? $C(t('admin.read', 'Read')) : $C(t('admin.unread', 'Unread')) ?></span> <?= (int)$row['replied'] ? '<span class="badge badge-active">' . $C(t('admin.replied', 'Replied')) . '</span>' : '' ?></strong></div>
        </div>
        <div class="field" style="margin-top:16px;">
          <label><?= $C(t('admin.message', 'Message')) ?></label>
          <div class="message-body" style="background:var(--bg-soft);padding:16px;border-radius:8px;line-height:1.7;white-space:pre-wrap;"><?= $C($row['message']) ?></div>
        </div>
        <div class="form-actions" style="margin-top:16px;">
          <a class="btn" href="mailto:<?= $C($row['email']) ?>?subject=<?= $C(rawurlencode('Re: ' . ($row['subject'] ?? 'Your message'))) ?>"><i class="fa-solid fa-reply"></i> <?= $C(t('admin.reply', 'Reply')) ?></a>
          <form method="post" action="view.php?id=<?= (int)$id ?>" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= (int)$row['replied'] ? 'unreplied' : 'replied' ?>">
            <button type="submit" class="btn btn-ghost"><?= $C((int)$row['replied'] ? t('admin.mark_unreplied', 'Mark as Unreplied') : t('admin.mark_replied', 'Mark as Replied')) ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>