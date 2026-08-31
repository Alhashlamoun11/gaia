<?php
$policy = $policy ?? '';
?>
<form method="post" action="" class="policy-card" style="padding:20px;">
  <?= csrf_field() ?>
  <div class="form-group" style="margin-bottom:15px;">
    <label for="policy" style="font-weight:bold;">Cancellation Policy</label>
    <textarea name="policy" id="policy" rows="10" style="width:100%;padding:8px;"><?= htmlspecialchars($policy) ?></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Save Policy</button>
</form>
