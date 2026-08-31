<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../admin/helpers/CrudService.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'rooms';
$hotel_page_title = t('hotel.rooms', 'Rooms');

$pdo = getPDO();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// If form is submitted with a hidden ID, override GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
}

$room = null;

if ($id) {
    // Security ownership check
    $stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE id = ? AND hotel_id = ?");
    $stmt->execute([$id, $my_hotel_id]);
    $room = $stmt->fetch();
    if (!$room) {
        die("Room not found or unauthorized.");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();

    $action = $_POST['action'] ?? '';

    if ($action === 'remove_gallery' && $id && $room) {
        $removeUrl = $_POST['remove_url'] ?? '';
        $gallery = json_decode((string)$room['gallery_urls'] ?: '[]', true) ?: [];
        $gallery = array_filter($gallery, fn($url) => $url !== $removeUrl);
        $galleryJson = json_encode(array_values($gallery));
        
        $stmt = $pdo->prepare("UPDATE hotel_rooms SET gallery_urls = ? WHERE id = ? AND hotel_id = ?");
        $stmt->execute([$galleryJson, $id, $my_hotel_id]);
        
        hotel_flash('Gallery image removed.', 'success');
        header("Location: room-form.php?id=$id");
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 2);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $price = (float)($_POST['price'] ?? 0);
    $beds = trim($_POST['beds'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $facilities = trim($_POST['facilities'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        hotel_flash('Room name is required.', 'error');
    } else {
        $imageUrl = $room['image_url'] ?? null;
        if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = CrudService::handleUpload($_FILES['main_image'], 'hotel_rooms');
            if ($up) $imageUrl = $up;
        }

        $gallery = json_decode((string)($room['gallery_urls'] ?? '[]'), true) ?: [];
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
                    $up = CrudService::handleUpload($singleFile, 'hotel_rooms');
                    if ($up) $gallery[] = $up;
                }
            }
        }
        $galleryJson = json_encode(array_values($gallery));

        if ($id) {
            $stmt = $pdo->prepare("UPDATE hotel_rooms SET name = ?, capacity = ?, quantity = ?, price = ?, beds = ?, description = ?, facilities = ?, is_active = ?, image_url = ?, gallery_urls = ? WHERE id = ? AND hotel_id = ?");
            $stmt->execute([$name, $capacity, $quantity, $price, $beds, $description, $facilities, $is_active, $imageUrl, $galleryJson, $id, $my_hotel_id]);
            hotel_flash('Room updated successfully.', 'success');
            Auth::logAudit('hotel_room_updated');
        } else {
            $stmt = $pdo->prepare("INSERT INTO hotel_rooms (hotel_id, name, capacity, quantity, price, beds, description, facilities, is_active, image_url, gallery_urls) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$my_hotel_id, $name, $capacity, $quantity, $price, $beds, $description, $facilities, $is_active, $imageUrl, $galleryJson]);
            $id = $pdo->lastInsertId();
            hotel_flash('Room created successfully.', 'success');
            Auth::logAudit('hotel_room_created');
        }
        header("Location: room-form.php?id=$id");
        exit;
    }
}

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="toolbar">
        <a href="rooms.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <h2><?= $id ? 'Edit Room' : 'Add Room' ?></h2>
      </div>

      <div class="card">
        <form method="post" action="room-form.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <?php if ($id): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
          <?php endif; ?>
          
          <div class="form-grid" style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:16px;">
            <div class="field">
              <label>Room Name <span style="color:#b3261e">*</span></label>
              <input type="text" name="name" value="<?= htmlspecialchars((string)($room['name'] ?? '')) ?>" required>
            </div>
            <div class="field">
              <label>Price <span style="color:#b3261e">*</span></label>
              <input type="number" step="0.01" name="price" value="<?= htmlspecialchars((string)($room['price'] ?? '0.00')) ?>" required>
            </div>
            <div class="field">
              <label>Capacity</label>
              <input type="number" name="capacity" value="<?= htmlspecialchars((string)($room['capacity'] ?? '2')) ?>">
            </div>
            <div class="field">
              <label>Quantity (Inventory)</label>
              <input type="number" min="1" name="quantity" value="<?= htmlspecialchars((string)($room['quantity'] ?? '1')) ?>">
            </div>
          </div>

          <div class="form-grid" style="display:grid; grid-template-columns:1fr; gap:16px; margin-top:16px;">
            <div class="field">
              <label>Bed Configuration</label>
              <input type="text" name="beds" value="<?= htmlspecialchars((string)($room['beds'] ?? '')) ?>" placeholder="e.g. 1 King Bed, 2 Single Beds">
            </div>

            <div class="field">
              <label>Description</label>
              <textarea name="description" rows="4"><?= htmlspecialchars((string)($room['description'] ?? '')) ?></textarea>
            </div>

            <div class="field">
              <label>Facilities (comma separated)</label>
              <textarea name="facilities" rows="2" placeholder="WiFi, AC, Minibar, TV..."><?= htmlspecialchars((string)($room['facilities'] ?? '')) ?></textarea>
            </div>

            <div class="field" style="display:flex; align-items:center; gap:8px;">
              <input type="checkbox" name="is_active" id="is_active" value="1" <?= (!$id || !empty($room['is_active'])) ? 'checked' : '' ?> style="width:auto; margin:0;">
              <label for="is_active" style="margin:0;">Room is Active (Bookable)</label>
            </div>
          </div>

          <h3 style="margin-top:30px; margin-bottom:20px;">Media</h3>
          <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
            
            <div class="field">
              <label>Room Main Image</label>
              <?php if (!empty($room['image_url'])): ?>
                <div style="margin-bottom:10px;">
                    <img src="<?= htmlspecialchars('/' . ltrim($room['image_url'], '/')) ?>" alt="Main Image" style="max-width:100%; max-height:200px; border-radius:8px; border:1px solid var(--line);">
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

          <div style="margin-top:24px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Room</button>
          </div>
        </form>
      </div>

      <?php
        if ($id) {
            $gallery = json_decode((string)$room['gallery_urls'] ?: '[]', true) ?: [];
            if (!empty($gallery)):
      ?>
      <div class="card" style="margin-top:24px;">
        <h3 style="margin-bottom:20px;">Room Gallery</h3>
        <div style="display:flex; flex-wrap:wrap; gap:16px;">
            <?php foreach($gallery as $img): ?>
                <div style="position:relative; width:150px; height:150px; border:1px solid var(--line); border-radius:8px; overflow:hidden;">
                    <img src="<?= htmlspecialchars('/' . ltrim($img, '/')) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <form method="post" action="room-form.php?id=<?= $id ?>" style="position:absolute; top:5px; right:5px;">
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
      <?php 
            endif; 
        }
      ?>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
