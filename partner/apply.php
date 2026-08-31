<?php
/**
 * partner/apply.php
 * ------------------------------------------------------------
 * Public form for Hotel Partner Onboarding.
 * Submits an application to the partner_applications table.
 * Does NOT create a hotel or a user account.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin/helpers/CrudService.php'; // For handleUpload

// Ensure session for CSRF and flashes
Auth::startSession();

$alerts = [];
$errors = [];
$successRef = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }

    $pdo = getPDO();
    
    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $hotel_name = trim($_POST['hotel_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $hotel_phone = trim($_POST['hotel_phone'] ?? '');
    $hotel_email = trim($_POST['hotel_email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $check_in_time = trim($_POST['check_in_time'] ?? '');
    $check_out_time = trim($_POST['check_out_time'] ?? '');
    $cancellation_policy = trim($_POST['cancellation_policy'] ?? '');
    $facilities = trim($_POST['facilities'] ?? '');
    
    // Validation
    if ($full_name === '') $errors[] = t('auth.first_name', 'Full Name is required'); // Reusing existing translation or fallback
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('auth.invalid_email', 'Invalid email address');
    if ($password === '' || strlen($password) < 8) $errors[] = t('auth.password_min', 'Password must be at least 8 characters');
    if ($password !== $confirm_password) $errors[] = t('auth.password_mismatch', 'Passwords do not match');
    if ($hotel_name === '') $errors[] = t('partner.hotel_name', 'Hotel Name') . ' ' . t('admin.is_required', 'is required');

    // Duplicate protection
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM partner_applications WHERE email = ? AND status IN ('pending', 'under_review') LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = t('partner.duplicate_application', 'An active application with this email already exists.');
        }
    }

    $main_image = null;
    if (!$errors && !empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['main_image'], 'hotels');
        if ($up !== null) {
            $main_image = $up;
        } else {
            $errors[] = t('admin.upload_failed', 'Image upload failed.');
        }
    }

    if (!$errors) {
        // Generate unique reference GAIA-P-XXXXXX
        $ref = 'GAIA-P-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare(
            'INSERT INTO partner_applications (
                reference_code, `type`, `status`,
                full_name, email, phone, password_hash,
                hotel_name, description, address, city, country,
                hotel_phone, hotel_email, website, check_in_time, check_out_time,
                cancellation_policy, facilities, main_image, created_at
            ) VALUES (
                ?, "hotel", "pending",
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, NOW()
            )'
        );

        try {
            $stmt->execute([
                $ref,
                $full_name, $email, $phone !== '' ? $phone : null, $hash,
                $hotel_name, $description !== '' ? $description : null, $address !== '' ? $address : null, $city !== '' ? $city : null, $country !== '' ? $country : null,
                $hotel_phone !== '' ? $hotel_phone : null, $hotel_email !== '' ? $hotel_email : null, $website !== '' ? $website : null,
                $check_in_time !== '' ? $check_in_time : null, $check_out_time !== '' ? $check_out_time : null,
                $cancellation_policy !== '' ? $cancellation_policy : null, $facilities !== '' ? $facilities : null, $main_image
            ]);
            $applicationId = (int)$pdo->lastInsertId();
            
            // Audit log
            Auth::logAudit('partner_application_created', $applicationId);

            $successRef = $ref;
            $alerts[] = ['type' => 'success', 'msg' => t_fmt('partner.apply_success', ['ref' => $ref], gaia_current_lang())];
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$gaia_base = '../';
$gaia_active = 'partner';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('partner_apply', t('partner.join_title', 'Join as a Partner')) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<link rel="stylesheet" href="/assets/styles.css">
<style>
/* Shared minimal design based on gaia auth/checkout styles */
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;}
body{margin:0;font-family:'Inter',sans-serif;background:var(--bg-soft);color:var(--ink);}
.container{max-width:1080px;margin:0 auto;padding:0 32px;}
.page-head{background:#fff;padding:40px 0;}
.page-head h1{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;}
.section{padding:40px 0;}
.form-card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:32px;margin-bottom:30px;}
.form-card h2{font-family:'Playfair Display',serif;font-size:22px;margin-top:0;margin-bottom:24px;border-bottom:1px solid var(--line);padding-bottom:16px;}
.field{margin-bottom:20px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:8px;}
.field input[type="text"], .field input[type="email"], .field input[type="password"], .field input[type="tel"], .field input[type="time"], .field select, .field textarea {width:100%;border:1px solid var(--line);border-radius:8px;padding:12px;font-family:inherit;font-size:14px;box-sizing:border-box;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.btn{background:var(--teal);color:#fff;border:none;border-radius:8px;padding:12px 24px;font-weight:600;cursor:pointer;font-size:15px;}
.btn:hover{background:var(--teal-dark);}
.alert{padding:16px;border-radius:8px;margin-bottom:20px;font-size:14px;}
.alert-error{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;}
.alert-success{background:#e6f4ea;color:#137333;border:1px solid #ceead6;}
.success-box {background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:40px;text-align:center;}
.success-box i {font-size:48px;color:var(--teal);margin-bottom:20px;}
.success-box h2 {font-family:'Playfair Display',serif;font-size:24px;margin-bottom:10px;}
.success-box .ref {display:inline-block;background:var(--bg-soft);padding:10px 20px;border-radius:8px;font-weight:700;font-size:18px;margin-top:20px;letter-spacing:1px;}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="page-head">
  <div class="container">
    <h1><?= htmlspecialchars(t('partner.join_title', 'Join as a Partner')) ?></h1>
  </div>
</div>

<div class="section">
  <div class="container">
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php foreach ($alerts as $a): ?>
      <div class="alert alert-<?= htmlspecialchars($a['type']) ?>"><?= htmlspecialchars($a['msg']) ?></div>
    <?php endforeach; ?>

    <?php if ($successRef): ?>
        <div class="success-box">
            <i class="fa-solid fa-circle-check"></i>
            <h2><?= htmlspecialchars(t('partner.apply_success_title', 'Application Submitted')) ?></h2>
            <p><?= htmlspecialchars(t('partner.apply_success_desc', 'Your application has been successfully submitted and is pending review by our team.')) ?></p>
            <div class="ref"><?= htmlspecialchars($successRef) ?></div>
        </div>
    <?php else: ?>
        <form method="post" action="apply.php" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            
            <div class="form-card">
                <h2><?= htmlspecialchars(t('partner.account_info', 'Account Information')) ?></h2>
                <div class="grid-2">
                    <div class="field">
                        <label><?= htmlspecialchars(t('auth.first_name', 'Full Name')) ?> *</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="field">
                        <label><?= htmlspecialchars(t('auth.email', 'Email')) ?> *</label>
                        <input type="email" name="email" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label><?= htmlspecialchars(t('auth.phone', 'Phone')) ?></label>
                        <input type="tel" name="phone">
                    </div>
                    <div></div>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label><?= htmlspecialchars(t('auth.password', 'Password')) ?> *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="field">
                        <label><?= htmlspecialchars(t('auth.confirm_password', 'Confirm Password')) ?> *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h2><?= htmlspecialchars(t('partner.hotel_info', 'Hotel Information')) ?></h2>
                <div class="field">
                    <label><?= htmlspecialchars(t('partner.hotel_name', 'Hotel Name')) ?> *</label>
                    <input type="text" name="hotel_name" required>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars(t('partner.description', 'Description')) ?></label>
                    <textarea name="description" rows="4"></textarea>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars(t('partner.address', 'Address')) ?></label>
                    <input type="text" name="address">
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label><?= htmlspecialchars(t('partner.city', 'City')) ?></label>
                        <input type="text" name="city">
                    </div>
                    <div class="field">
                        <label><?= htmlspecialchars(t('partner.country', 'Country')) ?></label>
                        <input type="text" name="country">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label><?= htmlspecialchars(t('partner.hotel_phone', 'Hotel Phone')) ?></label>
                        <input type="tel" name="hotel_phone">
                    </div>
                    <div class="field">
                        <label><?= htmlspecialchars(t('partner.hotel_email', 'Hotel Email')) ?></label>
                        <input type="email" name="hotel_email">
                    </div>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars(t('partner.website', 'Website')) ?></label>
                    <input type="text" name="website" placeholder="https://...">
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label><?= htmlspecialchars(t('partner.check_in_time', 'Check-in Time')) ?></label>
                        <input type="time" name="check_in_time">
                    </div>
                    <div class="field">
                        <label><?= htmlspecialchars(t('partner.check_out_time', 'Check-out Time')) ?></label>
                        <input type="time" name="check_out_time">
                    </div>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars(t('partner.cancellation_policy', 'Cancellation Policy')) ?></label>
                    <textarea name="cancellation_policy" rows="3"></textarea>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars(t('partner.facilities', 'Facilities')) ?></label>
                    <textarea name="facilities" rows="2" placeholder="e.g. WiFi, Pool, Gym"></textarea>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars(t('partner.main_image', 'Main Image')) ?></label>
                    <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>

            <button type="submit" class="btn"><?= htmlspecialchars(t('partner.submit_application', 'Submit Application')) ?></button>
        </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>

<script src="/assets/gaia.js"></script>
</body>
</html>
