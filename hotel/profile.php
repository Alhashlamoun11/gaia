<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../admin/helpers/CrudService.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'profile';
$hotel_page_title = t('hotel.profile', 'Profile');

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();

    $action = $_POST['action'] ?? '';

    // Fetch existing hotel to manipulate gallery and verify ownership
    $stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
    $stmt->execute([$my_hotel_id]);
    $hotel = $stmt->fetch();
    if (!$hotel) die("Hotel record not found.");

    if ($action === 'remove_gallery') {
        $removeUrl = $_POST['remove_url'] ?? '';
        $gallery = json_decode((string)$hotel['gallery_urls'] ?: '[]', true) ?: [];
        $gallery = array_filter($gallery, fn($url) => $url !== $removeUrl);
        $galleryJson = json_encode(array_values($gallery));
        
        $stmt = $pdo->prepare("UPDATE hotels SET gallery_urls = ? WHERE id = ?");
        $stmt->execute([$galleryJson, $my_hotel_id]);
        
        hotel_flash('Gallery image removed.', 'success');
        header('Location: profile.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $name_ar = trim($_POST['name_ar'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $description_ar = trim($_POST['description_ar'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $check_in = trim($_POST['check_in_time'] ?? '');
    $check_out = trim($_POST['check_out_time'] ?? '');
    $cancellation = trim($_POST['cancellation_policy'] ?? '');
    $star_rating = (int)($_POST['star_rating'] ?? 3);
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $location_ar = trim($_POST['location_ar'] ?? '');
    $seo_title = trim($_POST['seo_title'] ?? '');
    $seo_description = trim($_POST['seo_description'] ?? '');

    if ($name === '') {
        hotel_flash('Hotel name is required', 'error');
    } else {
        // Handle main image
        $imageUrl = $hotel['image_url'];
        if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = CrudService::handleUpload($_FILES['main_image'], 'hotels');
            if ($up) $imageUrl = $up;
        }

        // Handle gallery images
        $gallery = json_decode((string)$hotel['gallery_urls'] ?: '[]', true) ?: [];
        if (!empty($_FILES['gallery_images']['name'][0])) {
            $files = $_FILES['gallery_images'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $singleFile = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];
                    $up = CrudService::handleUpload($singleFile, 'hotels');
                    if ($up) $gallery[] = $up;
                }
            }
        }
        $galleryJson = json_encode(array_values($gallery));

        $stmt = $pdo->prepare("UPDATE hotels SET name = ?, name_ar = ?, description = ?, description_ar = ?, phone = ?, email = ?, website = ?, check_in_time = ?, check_out_time = ?, cancellation_policy = ?, image_url = ?, gallery_urls = ?, star_rating = ?, city = ?, country = ?, location = ?, location_ar = ?, seo_title = ?, seo_description = ? WHERE id = ?");
        $stmt->execute([$name, $name_ar, $description, $description_ar, $phone, $email, $website, $check_in, $check_out, $cancellation, $imageUrl, $galleryJson, $star_rating, $city, $country, $location, $location_ar, $seo_title, $seo_description, $my_hotel_id]);
        
        hotel_flash('Profile updated successfully.', 'success');
        Auth::logAudit('hotel_profile_updated');
    }
    
    header('Location: profile.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
$stmt->execute([$my_hotel_id]);
$hotel = $stmt->fetch();

if (!$hotel) {
    die("Hotel record not found.");
}

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="card">
        <form method="post" action="profile.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          
          <h3 style="margin-bottom:20px;">Basic Information</h3>
          
          <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="field">
              <label>Hotel Name (English) <span style="color:#b3261e">*</span></label>
              <input type="text" name="name" value="<?= htmlspecialchars((string)$hotel['name']) ?>" required>
            </div>
            <div class="field">
              <label>Hotel Name (Arabic)</label>
              <input type="text" name="name_ar" value="<?= htmlspecialchars((string)($hotel['name_ar'] ?? '')) ?>">
            </div>
          </div>

          <div class="field" style="margin-bottom:16px;">
            <label>Star Rating</label>
            <select name="star_rating" style="width:100%; padding:10px; border:1px solid var(--line); border-radius:10px;">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <option value="<?= $i ?>" <?= (int)($hotel['star_rating'] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <h3 style="margin-bottom:20px;">Location Information</h3>
          
          <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="field">
              <label>City</label>
              <input type="text" name="city" value="<?= htmlspecialchars((string)($hotel['city'] ?? '')) ?>">
            </div>
            <div class="field">
              <label>Country</label>
              <input type="text" name="country" value="<?= htmlspecialchars((string)($hotel['country'] ?? '')) ?>">
            </div>
          </div>

          <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="field">
              <label>Location/Address (English)</label>
              <input type="text" name="location" value="<?= htmlspecialchars((string)($hotel['location'] ?? '')) ?>">
            </div>
            <div class="field">
              <label>Location/Address (Arabic)</label>
              <input type="text" name="location_ar" value="<?= htmlspecialchars((string)($hotel['location_ar'] ?? '')) ?>">
            </div>
          </div>

          <h3 style="margin-bottom:20px;">Descriptions</h3>
          
          <div class="field" style="margin-bottom:16px;">
            <label>Description (English)</label>
            <textarea name="description" rows="4"><?= htmlspecialchars((string)$hotel['description']) ?></textarea>
          </div>

          <div class="field" style="margin-bottom:16px;">
            <label>Description (Arabic)</label>
            <textarea name="description_ar" rows="4"><?= htmlspecialchars((string)($hotel['description_ar'] ?? '')) ?></textarea>
          </div>

          <div class="form-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px;">
            <div class="field">
              <label>Phone</label>
              <input type="text" name="phone" value="<?= htmlspecialchars((string)$hotel['phone']) ?>">
            </div>
            <div class="field">
              <label>Email</label>
              <input type="email" name="email" value="<?= htmlspecialchars((string)$hotel['email']) ?>">
            </div>
            <div class="field">
              <label>Website</label>
              <input type="url" name="website" value="<?= htmlspecialchars((string)$hotel['website']) ?>">
            </div>
          </div>

          <h3 style="margin-bottom:20px;">Policies</h3>
          <div class="form-grid" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; margin-bottom:16px;">
            <div class="field">
              <label>Check-in Time</label>
              <input type="time" name="check_in_time" value="<?= htmlspecialchars((string)$hotel['check_in_time'] ?: '14:00') ?>">
            </div>
            <div class="field">
              <label>Check-out Time</label>
              <input type="time" name="check_out_time" value="<?= htmlspecialchars((string)$hotel['check_out_time'] ?: '12:00') ?>">
            </div>
          </div>

          <div class="field" style="margin-bottom:24px;">
            <label>Cancellation Policy</label>
            <textarea name="cancellation_policy" rows="3"><?= htmlspecialchars((string)$hotel['cancellation_policy']) ?></textarea>
          </div>

          <h3 style="margin-bottom:20px;">SEO Settings</h3>
          <div class="field" style="margin-bottom:16px;">
            <label>SEO Title</label>
            <input type="text" name="seo_title" value="<?= htmlspecialchars((string)($hotel['seo_title'] ?? '')) ?>">
          </div>
          <div class="field" style="margin-bottom:30px;">
            <label>SEO Description</label>
            <textarea name="seo_description" rows="3"><?= htmlspecialchars((string)($hotel['seo_description'] ?? '')) ?></textarea>
          </div>

          <h3 style="margin-bottom:20px;">Media</h3>
          <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
            
            <div class="field">
              <label>Main Hotel Image</label>
              <?php if (!empty($hotel['image_url'])): ?>
                <div style="margin-bottom:10px;">
                    <img src="<?= htmlspecialchars('/' . ltrim($hotel['image_url'], '/')) ?>" alt="Main Image" style="max-width:100%; max-height:200px; border-radius:8px; border:1px solid var(--line);">
                </div>
              <?php endif; ?>
              <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp">
              <small class="muted" style="display:block; margin-top:5px;">Select a new image to replace the current one.</small>
            </div>

            <div class="field">
              <label>Add Gallery Images</label>
              <input type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple>
              <small class="muted" style="display:block; margin-top:5px;">You can select multiple images to upload.</small>
            </div>
          </div>

          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= htmlspecialchars(t('hotel.save_changes', 'Save Changes')) ?></button>
        </form>
      </div>

      <?php
        $gallery = json_decode((string)$hotel['gallery_urls'] ?: '[]', true) ?: [];
        if (!empty($gallery)):
      ?>
      <div class="card" style="margin-top:24px;">
        <h3 style="margin-bottom:20px;">Hotel Gallery</h3>
        <div style="display:flex; flex-wrap:wrap; gap:16px;">
            <?php foreach($gallery as $img): ?>
                <div style="position:relative; width:150px; height:150px; border:1px solid var(--line); border-radius:8px; overflow:hidden;">
                    <img src="<?= htmlspecialchars('/' . ltrim($img, '/')) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <form method="post" action="profile.php" style="position:absolute; top:5px; right:5px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_gallery">
                        <input type="hidden" name="remove_url" value="<?= htmlspecialchars($img) ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px;" onclick="return confirm('Remove this image?');" aria-label="Remove Image">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
