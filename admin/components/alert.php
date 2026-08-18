<?php
/**
 * admin/components/alert.php
 * ------------------------------------------------------------
 * Reusable alert renderer. Uses the session flash set via
 * admin_flash(), or an explicit $admin_alert array/string.
 * ------------------------------------------------------------
 */
// Support the established $account_alerts pattern from existing modules
// AND the session flash / single $admin_alert contract.
if (empty($admin_alert) && !empty($account_alerts)) {
    foreach ((array)$account_alerts as $one) {
        if (is_string($one)) {
            $one = ['type' => 'success', 'msg' => $one];
        }
        $atype = $one['type'] ?? 'success';
        $atype = in_array($atype, ['success', 'error', 'info'], true) ? $atype : 'success';
        $msg   = $one['msg'] ?? '';
        if ($msg === '') continue;
        echo '<div class="alert alert-' . htmlspecialchars($atype) . '">' . htmlspecialchars($msg) . '</div>';
    }
    return;
}

$admin_alert = $admin_alert ?? admin_flash();
if (!$admin_alert) return;

// Accept a plain string or an array.
if (is_string($admin_alert)) {
    $admin_alert = ['type' => 'success', 'msg' => $admin_alert];
}
$atype = $admin_alert['type'] ?? 'success';
$atype = in_array($atype, ['success', 'error', 'info'], true) ? $atype : 'success';
$msg   = $admin_alert['msg'] ?? '';
if ($msg === '') return;
?>
<div class="alert alert-<?= htmlspecialchars($atype) ?>"><?= htmlspecialchars($msg) ?></div>
