<?php
/**
 * admin/hotels/form.php
 * Hotels create/edit form.
 * Admin can manage commission (%) and hotel profile details.
 * Hotel base price is set by hotel manager via room inventory and locked against direct admin override.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('hotels', $id) : null;
if ($editing && !$row) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found', 'Not Found').' — GAIA TOURS &amp; TRAVEL';
    require __DIR__ . '/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">' . htmlspecialchars(t('admin.not_found_text', 'Not found.')) . '</div></div></div></div></div>';
    require __DIR__ . '/../components/footer.php';
    exit;
}

$alerts = []; $errors = [];
$old = $row ?: [
    'name'=>'', 'slug'=>'', 'city'=>'', 'country'=>'', 'star_rating'=>3, 'price_from'=>0,
    'admin_commission_percent'=>10,
    'description'=>'','image_url'=>'','gallery_urls'=>'','featured'=>0,'is_active'=>1,
    'seo_title'=>'','seo_description'=>''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();

    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'star_rating' => max(1, min(5, (int)($_POST['star_rating'] ?? 3))),
        'admin_commission_percent' => max(0, min(100, (float)($_POST['admin_commission_percent'] ?? 0))),
        'description' => trim($_POST['description'] ?? ''),
        'featured' => !empty($_POST['featured']) ? 1 : 0,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
    ];

    // Locked price rule: Admin cannot manually overwrite hotel base price
    if ($editing) {
        $data['price_from'] = (float)($row['price_from'] ?? 0);
    } else {
        $data['price_from'] = 0.00;
    }

    if ($data['name'] === '') $errors[] = t('admin.name_req', 'Name is required.');
    if ($data['slug'] === '') $data['slug'] = strtolower(preg_replace('/[^a-z0-9]+/i','-', $data['name']));
    $data['slug'] = preg_replace('/-+/','-', trim($data['slug'],'-'));

    // image upload
    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['image_file'], 'hotels');
        if ($up !== null) $data['image_url'] = $up;
        else $errors[] = t('admin.image_invalid', 'Invalid image upload.');
    } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
        $data['image_url'] = trim($_POST['image_url']);
    }

    $gallery = trim($_POST['gallery_urls'] ?? '');
    $data['gallery_urls'] = $gallery !== '' ? $gallery : null;

    if (!$errors) {
        if ($editing) {
            CrudService::update('hotels', $id, $data);
            $alerts[] = ['type'=>'success','msg'=>t('admin.saved','Saved successfully. Commission updated.')];
        } else {
            $id = CrudService::insert('hotels', $data);
            $editing = true;
            $alerts[] = ['type'=>'success','msg'=>t('admin.created','Created successfully.')];
        }
        $row = CrudService::find('hotels', $id);
        $old = $row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['name']:t('admin.add_hotel','Add Hotel');
$admin_active = 'hotels';
?>
<?php require __DIR__.'/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__.'/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__.'/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__.'/../components/alert.php'; ?>

      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-hotel"></i> <?= htmlspecialchars(t('admin.hotel_info','Hotel Information & Commission')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list','Back to list')) ?></a>
        </div>

        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?> *</label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= htmlspecialchars($old['slug']) ?>" placeholder="auto-generated"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.city')) ?></label><input type="text" name="city" value="<?= htmlspecialchars($old['city']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.country')) ?></label><input type="text" name="country" value="<?= htmlspecialchars($old['country']) ?>"></div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.stars')) ?></label>
              <select name="star_rating">
                <?php for ($i=1;$i<=5;$i++): ?><option value="<?=$i?>" <?= (int)$old['star_rating']===$i?'selected':'' ?>><?=$i?> ★</option><?php endfor; ?>
              </select>
            </div>
            
            <!-- Locked Base Price (Managed by Hotel Partner) -->
            <div class="field">
              <label><?= htmlspecialchars(t('admin.price','Hotel Base Price')) ?> <span class="muted" style="font-weight:normal;">(Locked)</span></label>
              <div style="position:relative;">
                <input type="text" readonly value="$<?= number_format((float)($old['price_from'] ?? 0), 2) ?>" style="background:#f1f5f9; cursor:not-allowed; color:#475569; font-weight:700; padding-right:32px;" />
                <i class="fa-solid fa-lock" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
              </div>
              <small class="hint" style="color:#64748b; font-size:11.5px;"><i class="fa-solid fa-circle-info"></i> Set by hotel partner via room inventory rates.</small>
            </div>

            <!-- Editable Admin Commission -->
            <div class="field">
              <label><?= htmlspecialchars(t('admin.admin_commission','Admin Commission (%)')) ?> <span style="color:#b3261e;">*</span></label>
              <div style="position:relative;">
                <input type="number" step="0.01" min="0" max="100" name="admin_commission_percent" value="<?= htmlspecialchars((string)($old['admin_commission_percent'] ?? 0)) ?>" required style="font-weight:700; color:var(--navy); padding-right:32px;" id="adminCommissionInput" />
                <span style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-weight:700; color:var(--muted);">%</span>
              </div>
              <small class="hint" style="color:#10b981; font-size:11.5px;"><i class="fa-solid fa-check"></i> Platform revenue cut applied to room bookings.</small>
            </div>
          </div>

          <!-- Live Commission Calculation Preview -->
          <?php
            $baseRate = (float)($old['price_from'] ?? 0);
            $commPct = (float)($old['admin_commission_percent'] ?? 0);
            $finalRate = $baseRate * (1 + ($commPct / 100));
          ?>
          <div style="background:#f8fafc; border:1px solid var(--line); border-radius:8px; padding:12px 16px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-size:12.5px; color:var(--muted);">
              <strong>Pricing Breakdown Preview:</strong> Hotel Base: <strong>$<?= number_format($baseRate, 2) ?></strong> + Commission: <strong id="commPreviewText"><?= number_format($commPct, 1) ?>%</strong>
            </div>
            <div style="font-size:13px; font-weight:800; color:var(--navy);">
              Public Customer Starting Price: <span style="color:#10b981;" id="finalPriceText">$<?= number_format($finalRate, 2) ?></span>
            </div>
          </div>

          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.featured')) ?></label><input type="checkbox" name="featured" value="1" <?= (int)$old['featured']?'checked':'' ?>> <?= htmlspecialchars(t('admin.featured', 'Featured on Homepage')) ?></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= htmlspecialchars(t('admin.active', 'Active & Visible')) ?></div>
          </div>

          <div class="field"><label><?= htmlspecialchars(t('admin.description')) ?></label><textarea name="description" rows="4"><?= htmlspecialchars($old['description']) ?></textarea></div>

          <div class="grid-2">
            <div class="field">
              <label><?= htmlspecialchars(t('admin.image')) ?></label>
              <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= htmlspecialchars('/' . ltrim($old['image_url'], '/')) ?>" alt="" style="height:70px; border-radius:6px; object-fit:cover; margin-bottom:6px;"><br><?php endif; ?>
              <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:4px;">
              <input type="hidden" name="image_url" value="<?= htmlspecialchars($old['image_url'] ?? '') ?>">
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.gallery_urls','Gallery URLs (one per line)')) ?></label><textarea name="gallery_urls" rows="3" placeholder="https://...&#10;https://..."><?= htmlspecialchars($old['gallery_urls']) ?></textarea></div>
          </div>

          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.seo_title','SEO Title')) ?></label><input type="text" name="seo_title" value="<?= htmlspecialchars($old['seo_title']) ?>"></div>
            <div class="field" style="grid-column:span 2;"><label><?= htmlspecialchars(t('admin.seo_description','SEO Description')) ?></label><input type="text" name="seo_description" value="<?= htmlspecialchars($old['seo_description']) ?>"></div>
          </div>

          <div class="form-actions" style="margin-top:20px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= htmlspecialchars(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php"><?= htmlspecialchars(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var baseRate = <?= json_encode($baseRate) ?>;
  var commInput = document.getElementById('adminCommissionInput');
  var commText = document.getElementById('commPreviewText');
  var finalText = document.getElementById('finalPriceText');

  if (commInput && commText && finalText) {
    commInput.addEventListener('input', function() {
      var pct = parseFloat(this.value) || 0;
      commText.textContent = pct.toFixed(1) + '%';
      var finalP = baseRate * (1 + (pct / 100));
      finalText.textContent = '$' + finalP.toFixed(2);
    });
  }
});
</script>

<?php require __DIR__.'/../components/footer.php'; ?>
