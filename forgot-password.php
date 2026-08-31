<?php
/**
 * forgot-password.php
 * ------------------------------------------------------------
 * 3-step password reset with 6-digit OTP sent via email.
 *
 * Step 1 — Enter email  → issues OTP, stores email in session
 * Step 2 — Enter 6-digit code → validates OTP, stores token_hash
 * Step 3 — Set new password → calls Auth::resetPassword()
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/services/EmailService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';
$lang = gaia_current_lang();

Auth::startSession();

$step   = (int)($_SESSION['fp_step'] ?? 1); // 1 | 2 | 3 | 4 (done)
$errors = [];
$info   = '';

// ── Step reset if user returns to step 1 explicitly ─────────
if (isset($_GET['restart'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_token_hash']);
    header('Location: forgot-password.php');
    exit;
}

// ── POST handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { csrf_fail(); }

    $action = $_POST['action'] ?? '';

    // ── Step 1: Email submission ──────────────────────────────
    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('auth.email_required', 'Please enter a valid email address.');
        } else {
            $result = Auth::issueOtpCode($email);
            // Always show the same success message to avoid user enumeration
            $_SESSION['fp_email'] = $email;
            $_SESSION['fp_step']  = 2;

            if ($result !== null) {
                // Send OTP email (best-effort)
                EmailService::sendPasswordResetOtp(
                    $email,
                    $result['first_name'] ?: 'User',
                    $result['otp'],
                    $lang
                );
            }
            // Redirect to avoid form re-submission
            header('Location: forgot-password.php');
            exit;
        }
    }

    // ── Step 2: OTP verification ──────────────────────────────
    elseif ($action === 'verify_otp') {
        $email = $_SESSION['fp_email'] ?? '';
        // Merge 6 individual digit fields
        $otp = '';
        for ($i = 1; $i <= 6; $i++) {
            $otp .= preg_replace('/\D/', '', $_POST['d' . $i] ?? '');
        }
        $otp = substr($otp, 0, 6);

        if (strlen($otp) !== 6) {
            $errors[] = t('auth.otp_required', 'Please enter the full 6-digit code.');
            $step = 2;
        } elseif ($email === '') {
            $errors[] = t('auth.session_expired', 'Session expired. Please start again.');
            $step = 1;
        } else {
            $tokenHash = Auth::validateOtpCode($email, $otp);
            if ($tokenHash === null) {
                $errors[] = t('auth.otp_invalid', 'Invalid or expired code. Please try again.');
                $step = 2;
            } else {
                $_SESSION['fp_token_hash'] = $tokenHash;
                $_SESSION['fp_step']       = 3;
                header('Location: forgot-password.php');
                exit;
            }
        }
    }

    // ── Step 3: New password ──────────────────────────────────
    elseif ($action === 'reset_password') {
        $tokenHash = $_SESSION['fp_token_hash'] ?? '';
        $email     = $_SESSION['fp_email'] ?? '';
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['password_confirm'] ?? '';

        if ($tokenHash === '' || $email === '') {
            $errors[] = t('auth.session_expired', 'Session expired. Please start again.');
            $step = 1;
        } elseif (strlen($password) < Auth::PASSWORD_MIN_LENGTH) {
            $errors[] = t('auth.password_min', 'Password must be at least ' . Auth::PASSWORD_MIN_LENGTH . ' characters.');
            $step = 3;
        } elseif ($password !== $confirm) {
            $errors[] = t('auth.password_mismatch', 'Passwords do not match.');
            $step = 3;
        } else {
            // Look up the user_id from the stored token_hash
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                'SELECT pr.user_id, pr.token_hash FROM password_resets pr
                 JOIN users u ON u.id = pr.user_id
                 WHERE pr.token_hash = ? AND u.email = ? AND pr.used = 0 AND pr.expires_at > NOW()
                 LIMIT 1'
            );
            $stmt->execute([$tokenHash, $email]);
            $row = $stmt->fetch();

            if (!$row) {
                $errors[] = t('auth.otp_expired', 'Your reset session has expired. Please start again.');
                // Clear session
                unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_token_hash']);
                $step = 1;
            } else {
                // Reconstruct a dummy raw token — we need to call resetPassword which hashes the token
                // Since we already have the hash, update directly
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$newHash, (int)$row['user_id']]);
                $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token_hash = ?')
                    ->execute([$tokenHash]);

                // Clear session state
                unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_token_hash']);
                $_SESSION['fp_step'] = 4; // done

                header('Location: forgot-password.php');
                exit;
            }
        }
    }
}

// Re-read step from session after handling
if (empty($errors)) {
    $step = (int)($_SESSION['fp_step'] ?? 1);
}
$emailDisplay = htmlspecialchars($_SESSION['fp_email'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('forgot_password', t('auth.forgot_password', 'Forgot Password') . ' — ' . site_setting('company_name', 'GAIA TOURS & TRAVEL')) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;}
*{box-sizing:border-box;}
body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
.auth-wrap{min-height:calc(100vh - 140px);display:flex;align-items:center;justify-content:center;padding:48px 16px;}
.auth-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 10px 30px rgba(27,42,74,.08);width:100%;max-width:460px;padding:44px;}
.auth-card h1{font-family:'Playfair Display',serif;font-size:26px;margin:0 0 6px;}
.auth-card .sub{color:var(--muted);font-size:14px;margin:0 0 28px;line-height:1.6;}

/* Steps indicator */
.steps{display:flex;gap:0;margin-bottom:32px;position:relative;}
.steps::before{content:'';position:absolute;top:14px;left:14px;right:14px;height:2px;background:var(--line);z-index:0;}
.step-dot{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;position:relative;z-index:1;}
.step-dot .dot{width:28px;height:28px;border-radius:50%;background:var(--line);border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#aaa;transition:.3s;}
.step-dot.active .dot{background:var(--teal);color:#fff;box-shadow:0 0 0 4px rgba(31,111,143,.15);}
.step-dot.done .dot{background:#1b6e43;color:#fff;}
.step-dot .label{font-size:11px;color:var(--muted);font-weight:500;white-space:nowrap;}
.step-dot.active .label{color:var(--teal);font-weight:700;}

.field{margin-bottom:18px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input[type=email],
.field input[type=password],
.field input[type=text]{width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}

/* OTP 6-digit input */
.otp-group{display:flex;gap:10px;justify-content:center;margin:8px 0 4px;}
.otp-group input{width:48px;height:58px;text-align:center;font-size:24px;font-weight:700;border:1.5px solid var(--line);border-radius:12px;background:#fbfaf7;font-family:'Inter',monospace;transition:.2s;color:var(--navy);}
.otp-group input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.15);background:#fff;outline:none;}
.otp-group input.filled{border-color:var(--teal);background:#f0f8ff;}

.btn-primary{width:100%;background:var(--teal);border:none;border-radius:10px;padding:14px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-primary:hover{background:var(--teal-dark);transform:translateY(-1px);}
.btn-ghost{background:none;border:none;color:var(--teal);font-size:13px;font-weight:600;cursor:pointer;padding:0;text-decoration:underline;font-family:inherit;}
.auth-switch{text-align:center;margin-top:20px;font-size:14px;color:var(--muted);}
.auth-switch a{color:var(--teal);font-weight:600;text-decoration:none;}

.alert{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:11px 14px;font-size:13.5px;margin-bottom:18px;}
.alert ul{margin:0;padding-left:18px;}
.success-box{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:12px;padding:28px 24px;text-align:center;}
.success-box i{font-size:48px;color:#1b6e43;margin-bottom:16px;display:block;}
.success-box h2{margin:0 0 8px;font-size:20px;}
.success-box p{margin:0 0 20px;color:#276e46;font-size:14px;}
.info-box{background:#f0f6ff;border:1px solid #c8dff8;border-radius:10px;padding:12px 16px;font-size:13.5px;color:#1b4c7a;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
.info-box i{margin-top:2px;opacity:.7;}
</style>
</head>
<body>
<?php require __DIR__ . '/components/gaia-header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card">

    <!-- Step indicator -->
    <?php if ($step < 4): ?>
    <div class="steps" style="margin-bottom:32px;">
      <div class="step-dot <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">
        <div class="dot"><?= $step > 1 ? '<i class="fa-solid fa-check" style="font-size:11px;"></i>' : '1' ?></div>
        <span class="label"><?= t('auth.step_email', 'Email') ?></span>
      </div>
      <div class="step-dot <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">
        <div class="dot"><?= $step > 2 ? '<i class="fa-solid fa-check" style="font-size:11px;"></i>' : '2' ?></div>
        <span class="label"><?= t('auth.step_verify', 'Verify Code') ?></span>
      </div>
      <div class="step-dot <?= $step === 3 ? 'active' : '' ?>">
        <div class="dot">3</div>
        <span class="label"><?= t('auth.step_reset', 'New Password') ?></span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Errors -->
    <?php if ($errors): ?>
      <div class="alert"><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════ -->
    <!-- STEP 1: Enter Email                           -->
    <!-- ══════════════════════════════════════════════ -->
    <?php if ($step === 1): ?>
      <h1><?= htmlspecialchars(t('auth.forgot_password', 'Forgot Password?')) ?></h1>
      <p class="sub"><?= htmlspecialchars(t('auth.forgot_sub', 'Enter your account email and we\'ll send you a 6-digit verification code.')) ?></p>

      <form method="post" action="forgot-password.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_otp">
        <div class="field">
          <label for="fp-email"><i class="fa-regular fa-envelope" style="margin-right:4px;opacity:.6;"></i><?= t('auth.email', 'Email Address') ?></label>
          <input type="email" id="fp-email" name="email" required autofocus autocomplete="email" placeholder="you@example.com">
        </div>
        <button type="submit" class="btn-primary">
          <i class="fa-solid fa-paper-plane"></i>
          <?= htmlspecialchars(t('auth.send_code', 'Send Verification Code')) ?>
        </button>
      </form>

    <!-- ══════════════════════════════════════════════ -->
    <!-- STEP 2: Enter OTP Code                        -->
    <!-- ══════════════════════════════════════════════ -->
    <?php elseif ($step === 2): ?>
      <h1><?= htmlspecialchars(t('auth.enter_code', 'Enter Verification Code')) ?></h1>
      <p class="sub"><?= htmlspecialchars(t('auth.enter_code_sub', 'We sent a 6-digit code to your email. It expires in 10 minutes.')) ?></p>

      <?php if ($emailDisplay): ?>
      <div class="info-box">
        <i class="fa-regular fa-envelope"></i>
        <span><?= t('auth.code_sent_to', 'Code sent to') ?> <strong><?= $emailDisplay ?></strong></span>
      </div>
      <?php endif; ?>

      <form method="post" action="forgot-password.php" novalidate id="otpForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify_otp">
        <div class="field">
          <label><?= htmlspecialchars(t('auth.otp_label', '6-Digit Code')) ?></label>
          <div class="otp-group" id="otpGroup">
            <?php for($i=1;$i<=6;$i++): ?>
            <input type="text" name="d<?= $i ?>" id="d<?= $i ?>" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" aria-label="Digit <?= $i ?>">
            <?php endfor; ?>
          </div>
        </div>
        <button type="submit" class="btn-primary" id="otpSubmit" disabled>
          <i class="fa-solid fa-shield-halved"></i>
          <?= htmlspecialchars(t('auth.verify_code', 'Verify Code')) ?>
        </button>
      </form>

      <div class="auth-switch" style="margin-top:16px;">
        <span><?= t('auth.no_code', "Didn't receive it?") ?></span>
        <a href="forgot-password.php?restart=1"><?= htmlspecialchars(t('auth.resend', 'Try again')) ?></a>
      </div>

      <script>
      (function(){
        const inputs = document.querySelectorAll('#otpGroup input');
        const btn = document.getElementById('otpSubmit');

        function checkFull(){
          const full = [...inputs].every(i => i.value.match(/^[0-9]$/));
          btn.disabled = !full;
          inputs.forEach(i => i.classList.toggle('filled', i.value.match(/^[0-9]$/)));
        }

        inputs.forEach((inp, idx) => {
          inp.addEventListener('input', function(){
            // Strip non-digits and take only first char
            this.value = this.value.replace(/\D/g,'').slice(-1);
            checkFull();
            if (this.value && idx < inputs.length - 1) inputs[idx+1].focus();
          });
          inp.addEventListener('keydown', function(e){
            if (e.key === 'Backspace' && !this.value && idx > 0) {
              inputs[idx-1].focus();
              inputs[idx-1].value = '';
              checkFull();
            }
          });
          inp.addEventListener('paste', function(e){
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
            [...pasted].slice(0,6).forEach((ch, i) => {
              if (inputs[idx + i]) inputs[idx + i].value = ch;
            });
            const next = Math.min(idx + pasted.length, inputs.length - 1);
            inputs[next].focus();
            checkFull();
          });
        });

        if(inputs[0]) inputs[0].focus();
      })();
      </script>

    <!-- ══════════════════════════════════════════════ -->
    <!-- STEP 3: New Password                          -->
    <!-- ══════════════════════════════════════════════ -->
    <?php elseif ($step === 3): ?>
      <h1><?= htmlspecialchars(t('auth.new_password', 'Set New Password')) ?></h1>
      <p class="sub"><?= htmlspecialchars(t('auth.new_password_sub', 'Choose a strong password for your account.')) ?></p>

      <form method="post" action="forgot-password.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <div class="field">
          <label for="fp-password"><i class="fa-solid fa-lock" style="margin-right:4px;opacity:.6;"></i><?= t('auth.new_password_label', 'New Password') ?></label>
          <input type="password" id="fp-password" name="password" required autofocus autocomplete="new-password" minlength="<?= Auth::PASSWORD_MIN_LENGTH ?>" placeholder="Min <?= Auth::PASSWORD_MIN_LENGTH ?> characters">
        </div>
        <div class="field">
          <label for="fp-confirm"><i class="fa-solid fa-lock" style="margin-right:4px;opacity:.6;"></i><?= t('auth.confirm_password', 'Confirm Password') ?></label>
          <input type="password" id="fp-confirm" name="password_confirm" required autocomplete="new-password" placeholder="Repeat your password">
        </div>

        <!-- Live strength bar -->
        <div style="margin-bottom:18px;">
          <div style="height:4px;background:#eee;border-radius:4px;overflow:hidden;">
            <div id="strengthBar" style="height:100%;width:0%;background:#e53935;border-radius:4px;transition:.3s;"></div>
          </div>
          <div id="strengthLabel" style="font-size:12px;color:var(--muted);margin-top:4px;"></div>
        </div>

        <button type="submit" class="btn-primary">
          <i class="fa-solid fa-key"></i>
          <?= htmlspecialchars(t('auth.reset_password_title', 'Reset Password')) ?>
        </button>
      </form>

      <script>
      document.getElementById('fp-password').addEventListener('input', function(){
        const v = this.value;
        let score = 0;
        if(v.length >= 8) score++;
        if(/[A-Z]/.test(v)) score++;
        if(/[0-9]/.test(v)) score++;
        if(/[^A-Za-z0-9]/.test(v)) score++;
        const colors = ['#e53935','#fb8c00','#f9a825','#43a047','#1b6e43'];
        const labels = ['','Weak','Fair','Good','Strong','Very Strong'];
        const bar = document.getElementById('strengthBar');
        const lbl = document.getElementById('strengthLabel');
        bar.style.width = (score * 25) + '%';
        bar.style.background = colors[score];
        lbl.textContent = labels[score];
        lbl.style.color = colors[score];
      });
      </script>

    <!-- ══════════════════════════════════════════════ -->
    <!-- STEP 4: Done                                   -->
    <!-- ══════════════════════════════════════════════ -->
    <?php elseif ($step === 4): ?>
      <div class="success-box">
        <i class="fa-solid fa-circle-check"></i>
        <h2><?= htmlspecialchars(t('auth.reset_success_title', 'Password Reset!')) ?></h2>
        <p><?= htmlspecialchars(t('auth.reset_success_msg', 'Your password has been successfully updated. You can now log in with your new password.')) ?></p>
        <a href="login.php" class="btn-primary" style="display:inline-flex;text-decoration:none;width:auto;padding:12px 32px;">
          <i class="fa-solid fa-right-to-bracket"></i>
          <?= htmlspecialchars(t('auth.login', 'Sign In')) ?>
        </a>
      </div>
      <?php unset($_SESSION['fp_step']); ?>
    <?php endif; ?>

    <?php if ($step < 4 && $step !== 4): ?>
    <div class="auth-switch">
      <a href="login.php"><i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> <?= htmlspecialchars(t('auth.back_to_login', 'Back to Login')) ?></a>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
</body>
</html>
