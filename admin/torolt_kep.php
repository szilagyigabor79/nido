<?php
require __DIR__ . '/../config.php';
$kid = (int)($_GET['id'] ?? 0);
$iid = (int)($_GET['iid'] ?? 0);

$st = $pdo->prepare("SELECT kep_url FROM ingatlan_kepek WHERE id=?");
$st->execute([$kid]);
if ($row = $st->fetch()) {
  $p = __DIR__ . '/../' . $row['kep_url'];
  if (is_file($p)) @unlink($p);
}
$pdo->prepare("DELETE FROM ingatlan_kepek WHERE id=?")->execute([$kid]);
header('Location: szerkesztes.php?id=' . $iid);
