<?php
/**
 * admin/components/header.php
 * ------------------------------------------------------------
 * Admin panel standalone <head> block. Requires _bootstrap.php to
 * be loaded (gaia_current_lang, gaia_dir, t()).
 *
 * Expected variables (optional):
 *   $admin_page_title : string <title>
 * ------------------------------------------------------------
 */
$admin_page_title = $admin_page_title ?? (t('admin.dashboard', 'Admin Panel') . ' — GAIA TOURS &amp; TRAVEL');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($admin_page_title) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
