<?php
$f = hotel_flash();
if ($f):
    $cls = $f['type'] === 'error' ? 'alert-error' : 'alert-success';
?>
<div class="alert <?= $cls ?>" style="margin-bottom:20px; padding:12px 16px; border-radius:10px; background:<?= $f['type']==='error'?'#fdecea':'#e8f5e9' ?>; color:<?= $f['type']==='error'?'#b3261e':'#1b5e20' ?>;">
  <?= htmlspecialchars($f['msg']) ?>
</div>
<?php endif; ?>