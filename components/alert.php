<?php
/**
 * components/alert.php
 * ------------------------------------------------------------
 * REUSABLE alert / flash message renderer.
 *
 * Expected variable (set by the caller):
 *   $account_alerts : array of ['type' => 'success|error|info', 'msg' => string]
 *   (or a single $alert array with the same shape)
 * ------------------------------------------------------------
 */

if (!empty($account_alerts)):
  foreach ($account_alerts as $alert):
    $type = $alert['type'] ?? 'success';
    $type = in_array($type, ['success', 'error', 'info'], true) ? $type : 'success';
    $msg  = $alert['msg'] ?? '';
    if ($msg === '') continue;
?>
  <div class="alert alert-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div>
<?php endforeach; endif; ?>
