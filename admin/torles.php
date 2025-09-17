<?php
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ingatlanok.php'); exit; }

/* töröljük a képeket fájlrendszerből is (ha /uploads alatt vannak) */
$st = $pdo->prepare("SELECT kep_url FROM ingatlan_kepek WHERE ingatlan_id=?");
$st->execute([$id]);
foreach ($st->fetchAll() as $row) {
  $rel = $row['kep_url'];
  // csak biztonságosan, az admin gyökérhöz képest
  $path = realpath(__DIR__ . '/../' . $rel);
  $uploads = realpath(__DIR__ . '/../uploads');
  if ($path && $uploads && str_starts_with($path, $uploads) && is_file($path)) {
    @unlink($path);
  }
}

/* képtáblából törlés */
$pdo->prepare("DELETE FROM ingatlan_kepek WHERE ingatlan_id=?")->execute([$id]);

/* fő rekord törlése */
$pdo->prepare("DELETE FROM ingatlanok WHERE id=?")->execute([$id]);

header('Location: ingatlanok.php');
exit;
