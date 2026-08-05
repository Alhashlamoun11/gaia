<?php
/**
 * gaia-hero.php
 * ------------------------------------------------------------
 * Reusable hero banner component.
 * Renders a full-width hero with optional title, subtitle and
 * a translucent overlay. Accepts these variables before include:
 *   $gaia_hero_title    — main heading (string)
 *   $gaia_hero_subtitle — supporting text (string)
 *   $gaia_hero_image    — background image URL (string)
 *   $gaia_hero_children — optional HTML injected below subtitle
 * ------------------------------------------------------------
 */
$gaia_hero_title    = $gaia_hero_title    ?? 'Travel beyond expectations';
$gaia_hero_subtitle = $gaia_hero_subtitle ?? '';
$gaia_hero_image    = $gaia_hero_image    ?? 'https://images.unsplash.com/photo-1483401757487-2ee349d4f25f?w=1600&q=80';
$gaia_hero_children = $gaia_hero_children ?? '';
?>
<section class="gaia-hero" style="background-image:linear-gradient(180deg, rgba(15,15,25,.55), rgba(15,15,25,.7)), url('<?= htmlspecialchars($gaia_hero_image) ?>');">
  <div class="gaia-hero-inner">
    <h1><?= $gaia_hero_title ?></h1>
    <?php if ($gaia_hero_subtitle !== ''): ?>
      <p class="gaia-hero-sub"><?= $gaia_hero_subtitle ?></p>
    <?php endif; ?>
    <?= $gaia_hero_children ?>
  </div>
</section>
