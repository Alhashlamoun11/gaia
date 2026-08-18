<?php
/**
 * components/timeline.php
 * ------------------------------------------------------------
 * REUSABLE timeline / status-history renderer.
 *
 * Expected variables (set by the caller):
 *   $timeline : array of booking_logs rows with keys:
 *               action, description, created_at
 *   $timeline_title : string (optional translated heading)
 * ------------------------------------------------------------
 */
$timeline       = $timeline ?? [];
$timeline_title = $timeline_title ?? t('account.timeline', 'Timeline');
?>
<?php if (!empty($timeline)): ?>
  <div class="card">
    <h2><?= htmlspecialchars($timeline_title) ?></h2>
    <div class="timeline">
      <?php foreach ($timeline as $entry): ?>
        <div class="timeline-item">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-title">
              <?= htmlspecialchars(function_exists('timeline_action_label') ? timeline_action_label((string)($entry['action'] ?? '')) : ($entry['action'] ?? '')) ?>
            </div>
            <?php if (!empty($entry['description'])): ?>
              <div class="timeline-desc"><?= htmlspecialchars($entry['description']) ?></div>
            <?php endif; ?>
            <div class="timeline-date"><?= htmlspecialchars($entry['created_at'] ?? '') ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<style>
.timeline{position:relative;padding-left:24px;}
.timeline::before{content:'';position:absolute;left:7px;top:4px;bottom:4px;width:2px;background:var(--line,#e8e6e0);}
.timeline-item{position:relative;padding:0 0 18px;}
.timeline-item:last-child{padding-bottom:0;}
.timeline-dot{position:absolute;left:-22px;top:4px;width:12px;height:12px;border-radius:50%;background:var(--teal,#1f6f8f);border:2px solid #fff;box-shadow:0 0 0 2px var(--teal,#1f6f8f);}
.timeline-title{font-weight:600;font-size:14px;}
.timeline-desc{font-size:13px;color:var(--muted,#6b7280);margin-top:2px;}
.timeline-date{font-size:12px;color:var(--muted,#6b7280);margin-top:2px;}
</style>
