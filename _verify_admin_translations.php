<?php
require __DIR__ . '/db.php';
$pdo = getPDO();

$adminDir = __DIR__ . '/admin';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adminDir));
$phpFiles = [];

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

$keysUsed = [];

// Regex to find t('key') or t("key") or t('key', 'default')
$pattern = "/t\(\s*['\"]([^'\"]+)['\"]/i";

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[1] as $key) {
            $keysUsed[$key] = true;
        }
    }
}

$keysUsed = array_keys($keysUsed);
sort($keysUsed);

echo "Found " . count($keysUsed) . " unique translation keys used in the admin panel.\n\n";

$stmtEn = $pdo->prepare("SELECT COUNT(*) FROM translations WHERE `key` = ? AND lang = 'en'");
$stmtAr = $pdo->prepare("SELECT COUNT(*) FROM translations WHERE `key` = ? AND lang = 'ar'");

$missingEn = [];
$missingAr = [];

foreach ($keysUsed as $key) {
    $stmtEn->execute([$key]);
    $enCount = (int)$stmtEn->fetchColumn();
    if ($enCount === 0) {
        $missingEn[] = $key;
    }

    $stmtAr->execute([$key]);
    $arCount = (int)$stmtAr->fetchColumn();
    if ($arCount === 0) {
        $missingAr[] = $key;
    }
}

if (empty($missingEn)) {
    echo "PASS: All used keys have English (EN) translations.\n";
} else {
    echo "FAIL: Missing EN translations for " . count($missingEn) . " keys.\n";
    foreach ($missingEn as $k) {
        echo "  - $k\n";
    }
}

echo "\n";

if (empty($missingAr)) {
    echo "PASS: All used keys have Arabic (AR) translations.\n";
} else {
    echo "FAIL: Missing AR translations for " . count($missingAr) . " keys.\n";
    foreach ($missingAr as $k) {
        echo "  - $k\n";
    }
}
