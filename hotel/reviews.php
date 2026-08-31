<?php
require_once __DIR__ . '/_bootstrap.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'reviews';
$hotel_page_title = t('hotel.reviews', 'Reviews');

$pdo = getPDO();

$stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.module_type = 'hotel' AND r.module_id = ? ORDER BY r.id DESC");
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
        <h2>Guest Reviews</h2>
      </div>

      <div class="card" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Guest</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($reviews as $r): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                  <td><span class="status-badge gold"><i class="fa-solid fa-star"></i> <?= (int)$r['rating'] ?>/5</span></td>
                  <td style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($r['comment']) ?>"><?= htmlspecialchars($r['comment']) ?></td>
                  <td>
                    <?php if($r['status'] === 'approved'): ?>
                      <span class="status-badge green">Approved</span>
                    <?php elseif($r['status'] === 'rejected'): ?>
                      <span class="status-badge red">Rejected</span>
                    <?php else: ?>
                      <span class="status-badge gray">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td><small style="color:var(--muted);"><?= htmlspecialchars($r['created_at']) ?></small></td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$reviews): ?>
                <tr><td colspan="5"><div class="muted" style="text-align:center;padding:18px;">No reviews found.</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <p style="color:var(--muted); font-size:12.5px; margin-top:12px;"><i class="fa-solid fa-info-circle"></i> Reviews are moderated by Super Admins before being published publicly.</p>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
