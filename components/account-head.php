<?php
/**
 * components/account-head.php
 * ------------------------------------------------------------
 * REUSABLE <head> + shared CSS for all account/customer pages.
 * Removes the large duplicated <style> blocks that previously
 * repeated in every account page.
 *
 * Expected (optional) variables:
 *   $gaia_page_key      : seo_meta page key (default 'account_dashboard')
 *   $gaia_page_title    : <title> string (falls back to GAIA branding)
 *
 * This component requires bootstrap.php + gaia-config.php to
 * already be loaded (gaia_current_lang, gaia_dir, gaia_seo_tags).
 * ------------------------------------------------------------
 */
$gaia_page_key   = $gaia_page_key   ?? 'account_dashboard';
$gaia_page_title = $gaia_page_title ?? (t('account.dashboard', 'My Account') . ' — GAIA TOURS &amp; TRAVEL');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags(htmlspecialchars($gaia_page_key), $gaia_page_title) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--sand:#d9a441;--gold:#d9a441;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}
body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
h1,h2,h3,h4{font-family:'Playfair Display',serif;margin:0;}
p{margin:0;}
a{text-decoration:none;color:inherit;}
img{max-width:100%;display:block;}
button{font-family:inherit;cursor:pointer;}
.account-shell{max-width:1280px;margin:0 auto;padding:32px;}
.account-layout{display:grid;grid-template-columns:260px 1fr;gap:26px;align-items:start;}
.account-main{min-width:0;}
/* Sidebar */
.account-sidebar{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:var(--shadow);}
.account-sidebar-user{display:flex;gap:12px;align-items:center;padding-bottom:16px;border-bottom:1px solid var(--line);margin-bottom:14px;}
.account-sidebar-user strong{display:block;font-size:14.5px;}
.account-sidebar-user span{display:block;font-size:12px;color:var(--muted);word-break:break-all;}
.account-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;background:var(--navy);flex:none;}
.account-nav{display:flex;flex-direction:column;gap:4px;}
.account-nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:10px;font-size:14px;color:var(--ink);transition:.15s;}
.account-nav a:hover{background:#f4efe6;}
.account-nav a.active{background:var(--navy);color:#fff;font-weight:600;}
.account-nav i{width:18px;text-align:center;}
.account-logout-form{margin-top:16px;border-top:1px solid var(--line);padding-top:14px;}
.account-logout-btn{width:100%;display:flex;align-items:center;gap:11px;padding:10px 12px;border:none;background:none;border-radius:10px;font-size:14px;color:#b3261e;cursor:pointer;font-family:inherit;}
.account-logout-btn:hover{background:#fdecea;}
/* Cards */
.card{background:var(--white);border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:22px;}
.card h2{font-size:20px;margin-bottom:6px;}
.card h3{font-size:16px;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.card h3 i{color:var(--teal);}
.card .sub{color:var(--muted);font-size:14px;margin:0 0 18px;}
/* Badges */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
.badge-confirmed,.badge-paid,.badge-completed,.badge-active,.badge-verified,.badge-issued,.badge-authorized{background:#e8f5ee;color:#1b6e43;}
.badge-pending,.badge-awaiting_payment,.badge-draft,.badge-unverified{background:#fff3d6;color:#8a6d00;}
.badge-cancelled,.badge-failed{background:#fdecea;color:#b3261e;}
.badge-refunded,.badge-void{background:#f0ece6;color:#6b6060;}
/* Buttons / links */
.btn{display:inline-flex;align-items:center;gap:7px;background:var(--teal);color:#fff;border:none;border-radius:10px;padding:11px 20px;font-size:14px;font-weight:700;transition:.2s;}
.btn:hover{background:var(--teal-dark);}
.btn-secondary{background:var(--navy);}
.btn-ghost{background:#fbfaf7;color:var(--ink);border:1px solid var(--line);}
.btn-ghost:hover{background:#f4efe6;}
.btn-danger{background:#b3261e;}
.btn-danger:hover{background:#8f1d17;}
.btn-sm{padding:8px 14px;font-size:13px;border-radius:8px;}
.btn-link{color:var(--teal);font-weight:600;font-size:13px;}
.btn-link:hover{text-decoration:underline;}
/* Filters */
.filters{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;align-items:center;}
.filters select,.filters input,.filters a.filter-btn,.filters .filter-btn{padding:10px 14px;border:1px solid var(--line);border-radius:10px;font-size:13.5px;font-family:inherit;background:#fbfaf7;color:var(--ink);}
.filters a.filter-btn{display:inline-flex;align-items:center;gap:6px;text-decoration:none;cursor:pointer;}
.filters a.filter-btn:hover{background:#f4efe6;}
/* Tables */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;min-width:560px;}
th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #f0ede6;font-size:13.5px;}
th{font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);}
td .code{font-weight:700;}
/* Lists */
.list-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0ede6;font-size:13.5px;flex-wrap:wrap;}
.list-item:last-child{border-bottom:none;}
.list-item .label{color:var(--muted);}
.list-item .value{font-weight:600;text-align:right;word-break:break-word;}
.list-item.total{font-weight:700;font-size:15px;}
.list-item.total .value{color:var(--teal);}
/* Fields */
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input, .field select, .field textarea{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
.field input[disabled]{opacity:.6;cursor:not-allowed;}
.avatar-preview{width:72px;height:72px;border-radius:50%;object-fit:cover;background:var(--navy);margin-bottom:12px;}
.avatar-hint{font-size:12px;color:var(--muted);margin-top:6px;}
.field input[type=file]{padding:10px;background:#fbfaf7;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
/* Alerts */
.alert{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:10px;padding:12px 14px;font-size:13.5px;margin-bottom:18px;}
.alert-error{background:#fdecea;color:#b3261e;border-color:#f5c6c2;}
.alert-success{background:#e8f5ee;color:#1b6e43;border-color:#b9e4cb;}
.alert-info{background:#e8f1fb;color:#1b5a8f;border-color:#c5dcf5;}
.alert ul{margin:0;padding-left:18px;}
/* Breadcrumbs */
.breadcrumbs{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;color:var(--muted);margin-bottom:18px;}
.breadcrumbs a{color:var(--teal);}
.breadcrumbs a:hover{text-decoration:underline;}
.breadcrumbs .sep{opacity:.5;}
.breadcrumbs .current{font-weight:600;color:var(--ink);}
/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stats-grid.two{grid-template-columns:repeat(2,1fr);}
.stats-grid.three{grid-template-columns:repeat(3,1fr);}
.stat-card{background:var(--white);border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:16px;transition:.2s;}
a.stat-card:hover{transform:translateY(-3px);box-shadow:0 14px 34px rgba(27,42,74,.14);}
.stat-card .stat-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex:none;}
.stat-card .stat-value{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;line-height:1;}
.stat-card .stat-label{color:var(--muted);font-size:12.5px;margin-top:4px;}
.stat-icon.blue{background:var(--teal);}.stat-icon.green{background:#2eae6e;}.stat-icon.gold{background:#d9a441;}.stat-icon.red{background:#d96a4a;}
/* Welcome card */
.welcome-card{background:var(--navy);color:#fff;border-radius:18px;padding:28px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.welcome-card h1{font-size:24px;}
.welcome-card p{margin:6px 0 0;color:#c7c3e2;font-size:14px;}
.welcome-actions{display:flex;gap:10px;flex-wrap:wrap;}
/* Card grid */
.card-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px;}
/* Quick actions */
.actions-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:16px;}
.action-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow);text-align:center;transition:.2s;display:flex;flex-direction:column;align-items:center;gap:8px;font-size:13px;font-weight:600;}
.action-card:hover{transform:translateY(-3px);box-shadow:0 14px 34px rgba(27,42,74,.14);}
.action-card i{font-size:24px;color:var(--teal);}
/* Tabs */
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;border-bottom:1px solid var(--line);padding-bottom:12px;}
.tab{padding:9px 18px;border-radius:20px;font-size:13.5px;font-weight:600;color:var(--muted);cursor:pointer;background:#fbfaf7;border:1px solid var(--line);}
.tab:hover{background:#f4efe6;}
.tab.active{background:var(--navy);color:#fff;border-color:var(--navy);}
/* Booking cards grid */
.booking-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px;}
.payment-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;}
.invoice-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;}
.fav-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;}
.fav-card{background:#fbfaf7;border:1px solid var(--line);border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:12px;}
.fav-card-top{display:flex;justify-content:space-between;align-items:center;gap:8px;}
.fav-card-name{font-weight:600;font-family:'Playfair Display',serif;font-size:15px;}
.fav-hint{margin-top:18px;padding-top:14px;border-top:1px solid var(--line);color:var(--muted);font-size:12.5px;display:flex;align-items:center;gap:8px;}
.notif-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;}
.notif-list{display:flex;flex-direction:column;}
.notif-item{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0ede6;align-items:flex-start;}
.notif-item:last-child{border-bottom:none;}
.notif-item.unread{background:#fbfaf7;margin:0 -14px;padding:14px;border-radius:10px;}
.notif-icon{width:40px;height:40px;border-radius:10px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;flex:none;}
.notif-body{flex:1;min-width:0;}
.notif-title{font-weight:600;font-size:14px;}
.notif-type{font-size:11.5px;color:var(--teal);text-transform:uppercase;letter-spacing:.3px;margin-top:2px;}
.notif-message{font-size:13px;color:var(--muted);margin-top:4px;}
.notif-meta{display:flex;flex-direction:column;align-items:flex-end;gap:8px;font-size:12px;flex:none;}
.inline-form{display:inline;}
.btn-danger-link{color:#b3261e;}
.btn-danger-link:hover{color:#8f1d17;}
.account-badge{margin-left:auto;background:var(--gold,#d9a441);color:#1b2a4a;border-radius:20px;padding:1px 8px;font-size:11px;font-weight:800;}
/* Misc */
.muted{color:var(--muted);}
.empty{color:var(--muted);font-size:14px;padding:20px 0;text-align:center;}
.lazy{opacity:0;transition:opacity .3s;}

@media (max-width:1100px){.stats-grid{grid-template-columns:1fr 1fr;}.card-grid{grid-template-columns:1fr;}}
@media (max-width:860px){
  .account-layout{grid-template-columns:1fr;}
  .account-nav{flex-direction:row;flex-wrap:wrap;}
  .grid-2,.grid-3{grid-template-columns:1fr;}
  .stats-grid{grid-template-columns:1fr;}
  .card-grid{grid-template-columns:1fr;}
  .actions-grid{grid-template-columns:1fr 1fr;}
  .account-shell{padding:20px 16px;}
}
</style>

