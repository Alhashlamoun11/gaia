<?php
/**
 * account/security.php
 * ------------------------------------------------------------
 * Customer security settings.
 *
 * Features:
 *   - Change password (requires current password, validate new +
 *     confirmation, uses password_hash()/password_verify())
 *   - Account security information (created date, last login,
 *     email verification status)
 *
 * SECURITY:
 *   - CSRF protection on all POSTs
 *   - PDO prepared statements
 *   - password_verify() on the current password before updating
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../services/SessionService.php';
require_once __DIR__ . '/../services/EmailService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

require_once __DIR__ . '/../auth/Auth.php';
$user   = auth_user();
$userId = (int)$user['id'];

$alerts = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }

    $current       = $_POST['current_password'] ?? '';
    $newPassword   = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($current === '') {
        $errors[] = t('account.current_password_required', 'Current password is required.');
    }
    if (strlen($newPassword) < Auth::PASSWORD_MIN_LENGTH) {
        $errors[] = t_fmt('account.password_min_length', ['min' => Auth::PASSWORD_MIN_LENGTH], gaia_current_lang());
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = t('account.password_mismatch', 'New passwords do not match.');
    }

    if (!$errors) {
        // Verify the current password against the stored hash.
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if ($hash === false || !password_verify($current, $hash)) {
            $errors[] = t('account.current_password_wrong', 'Current password is incorrect.');
        } elseif ($newPassword !== '' && password_verify($newPassword, $hash)) {
            $errors[] = t('account.password_same', 'New password must be different from the current password.');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
            $upd->execute([':hash' => $newHash, ':id' => $userId]);
            $alerts[] = ['type' => 'success', 'msg' => t('account.password_updated', 'Password updated successfully.')];
            // Send password changed notification email (best-effort)
            $firstName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            EmailService::sendPasswordChanged(
                $user['email'] ?? '',
                $firstName ?: 'User',
                gaia_current_lang()
            );
        }
    }
}

// Record the current session (best-effort) + list recent sessions.
SessionService::record($userId, $_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '');
$sessions = SessionService::listRecent($userId, 10);
$currentHash = SessionService::currentHash();

$gaia_page_key   = 'account_security';
$gaia_page_title = t('account.security') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'security';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<?php require __DIR__ . '/../components/account-head.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php
    $crumbs = [
        ['label' => t('account.dashboard'), 'url' => gaia_url('account/index.php')],
        ['label' => t('account.security'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <?php
        $account_alerts = array_merge(
            array_map(function ($e) { return ['type' => 'error', 'msg' => $e]; }, $errors),
            $alerts
        );
        require __DIR__ . '/../components/alert.php';
      ?>

<div class="card">
        <h2><?= htmlspecialchars(t('account.change_password')) ?></h2>

        <form method="post" action="security.php" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="current_password"><?= htmlspecialchars(t('account.current_password')) ?> *</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
          </div>
          <div class="field">
            <label for="new_password"><?= htmlspecialchars(t('account.new_password')) ?> *</label>
            <input type="password" id="new_password" name="new_password" required autocomplete="new-password" oninput="checkStrength(this.value)">
          </div>
          <div class="field">
            <label><?= htmlspecialchars(t('account.password_strength')) ?></label>
            <div class="strength-meter">
              <div class="strength-seg" id="seg1"></div>
              <div class="strength-seg" id="seg2"></div>
              <div class="strength-seg" id="seg3"></div>
              <div class="strength-seg" id="seg4"></div>
            </div>
            <div class="strength-label" id="strengthLabel"></div>
          </div>
          <div class="field">
            <label for="confirm_password"><?= htmlspecialchars(t('account.confirm_password')) ?> *</label>
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn-primary"><?= htmlspecialchars(t('account.change_password')) ?></button>
        </form>
      </div>

      <div class="card">
        <h2><?= htmlspecialchars(t('account.security_info')) ?></h2>
        <div class="list-item">
          <span class="label"><?= htmlspecialchars(t('account.created_at')) ?></span>
          <span class="value"><?= htmlspecialchars($user['created_at'] ?? '—') ?></span>
        </div>
        <div class="list-item">
          <span class="label"><?= htmlspecialchars(t('account.last_login')) ?></span>
          <span class="value"><?= htmlspecialchars($user['last_login_at'] ? $user['last_login_at'] : '—') ?></span>
        </div>
        <div class="list-item">
          <span class="label"><?= htmlspecialchars(t('account.email_verified')) ?></span>
          <span class="value">
            <?php if (!empty($user['email_verified_at'])): ?>
              <span class="badge badge-verified"><?= htmlspecialchars(t('account.verified')) ?></span>
            <?php else: ?>
              <span class="badge badge-unverified"><?= htmlspecialchars(t('account.unverified')) ?></span>
            <?php endif; ?>
          </span>
        </div>
      </div>

      <div class="card">
        <div class="notif-head">
          <h2><i class="fa-solid fa-laptop"></i> <?= htmlspecialchars(t('account.recent_sessions')) ?></h2>
          <button type="button" class="btn btn-sm btn-ghost" onclick="alert('<?= htmlspecialchars(t('account.logout_all_placeholder')) ?>')">
            <i class="fa-solid fa-right-from-bracket"></i> <?= htmlspecialchars(t('account.logout_all')) ?>
          </button>
        </div>
        <?php if (!$sessions): ?>
          <p class="sub"><?= htmlspecialchars(t('account.no_payment_records', 'No sessions recorded yet.')) ?></p>
        <?php else: ?>
          <?php foreach ($sessions as $s): ?>
            <?php $isCurrent = ($currentHash !== '' && hash_equals((string)$currentHash, (string)($s['session_hash'] ?? ''))); ?>
            <div class="list-item">
              <div>
                <strong><?= htmlspecialchars($s['device'] ?? '—') ?></strong>
                <div class="muted"><?= htmlspecialchars($s['ip_address'] ?: '—') ?> · <?= htmlspecialchars($s['last_activity'] ?: ($s['created_at'] ?? '')) ?></div>
              </div>
              <span>
                <?php if ($isCurrent): ?>
                  <span class="badge badge-active"><?= htmlspecialchars(t('account.current_session')) ?></span>
                <?php else: ?>
                  <span class="badge badge-draft"><?= htmlspecialchars(t('account.signed_out', 'Signed out')) ?></span>
                <?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<style>
.strength-meter{display:flex;gap:6px;margin-top:6px;}
.strength-seg{flex:1;height:8px;border-radius:6px;background:#eef0f4;transition:.2s;}
.strength-label{font-size:12.5px;color:var(--muted,#6b7280);margin-top:6px;font-weight:600;}
</style>
<script>
function checkStrength(v){
  var score=0;
  if(v.length>=8)score++;
  if(v.length>=12)score++;
  if(/[a-z]/.test(v)&&/[A-Z]/.test(v))score++;
  if(/\d/.test(v))score++;
  if(/[^A-Za-z0-9]/.test(v))score++;
var segs=[document.getElementById('seg1'),document.getElementById('seg2'),document.getElementById('seg3'),document.getElementById('seg4')];
  var colors=['#d96a4a','#e0a23c','#5aa24a','#2eae6e'];
  var labelEl=document.getElementById('strengthLabel');
  var filled=Math.min(4,Math.max(1,Math.min(4,Math.floor(score))));
  for(var i=0;i<4;i++){
    segs[i].style.background=i<filled?colors[filled-1]:'#eef0f4';
  }
var t=score<=1?'weak':score===2?'fair':score===3?'good':score>=4?'strong':'very_strong';
  var labels={weak:'<?= htmlspecialchars(t('account.strength_weak')) ?>',fair:'<?= htmlspecialchars(t('account.strength_fair')) ?>',good:'<?= htmlspecialchars(t('account.strength_good')) ?>',strong:'<?= htmlspecialchars(t('account.strength_strong')) ?>',very_strong:'<?= htmlspecialchars(t('account.strength_very_strong')) ?>'};
  labelEl.textContent=labels[t]||'';
}
</script>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>
