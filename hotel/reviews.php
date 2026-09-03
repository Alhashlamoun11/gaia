<?php
require_once __DIR__ . '/_bootstrap.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'reviews';
$hotel_page_title = t('hotel.reviews', 'Reviews');

$pdo = getPDO();

$stmt = $pdo->prepare("SELECT r.* FROM hotel_reviews r WHERE r.hotel_id = ? ORDER BY r.id DESC");
$stmt->execute([$my_hotel_id]);
$reviews = $stmt->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="toolbar">
        <h2>Guest Reviews & Feedback</h2>
        <div class="spacer" style="flex:1;"></div>
        <span style="font-size:13px; color:var(--muted); font-weight:600;"><?= count($reviews) ?> Total Reviews</span>
      </div>

      <div class="card" style="padding:0; overflow:hidden;">
        <div class="table-wrap" style="box-shadow:none; border:none;">
          <table>
            <thead>
              <tr>
                <th>Guest</th>
                <th>Rating</th>
                <th>Review Content</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($reviews as $r): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['reviewer'] ?? 'Guest') ?></strong></td>
                  <td><span class="status-badge gold"><i class="fa-solid fa-star"></i> <?= (int)$r['rating'] ?>/5</span></td>
                  <td style="max-width:320px; white-space:normal; line-height:1.4;"><?= htmlspecialchars($r['text'] ?? '') ?></td>
                  <td>
                    <?php if(!empty($r['is_active'])): ?>
                      <span class="status-badge green">Approved</span>
                    <?php else: ?>
                      <span class="status-badge gray">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td><small style="color:var(--muted); white-space:nowrap;"><?= htmlspecialchars($r['created_at']) ?></small></td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$reviews): ?>
                <tr><td colspan="5"><div class="muted" style="text-align:center;padding:24px;">No reviews recorded yet.</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <p style="color:var(--muted); font-size:12.5px; margin-top:12px; display:flex; align-items:center; gap:6px;">
        <i class="fa-solid fa-circle-info"></i> Reviews are moderated by Super Admins before being published publicly.
      </p>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
