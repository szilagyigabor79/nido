<?php
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/../config.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  die("Érvénytelen ID");
}

// --- alap adatok ---
$data = [
  'tipus'       => $_POST['tipus'] ?? '',
  'varos'       => $_POST['varos'] ?? '',
  'utca'        => $_POST['utca'] ?? '',
  'emelet'      => $_POST['emelet'] ?? null,
  'alapterulet' => $_POST['alapterulet'] ?? null,
  'szobaszam'   => $_POST['szobaszam'] ?? null,
  'felszoba'    => $_POST['felszoba'] ?? null,
  'ar_ft'       => $_POST['ar_ft'] ?? null,
  'leiras'      => $_POST['leiras'] ?? '',
  'falazat'     => $_POST['falazat'] ?? '',
  'allapot'     => $_POST['allapot'] ?? '',
  'statusz'     => $_POST['statusz'] ?? 'Aktív',
  'jeloles'     => $_POST['jeloles'] ?? '',
];

// --- frissítés ---
$sql = "UPDATE ingatlanok SET
          tipus=:tipus,
          varos=:varos,
          utca=:utca,
          emelet=:emelet,
          alapterulet=:alapterulet,
          szobaszam=:szobaszam,
          felszoba=:felszoba,
          ar_ft=:ar_ft,
          leiras=:leiras,
          falazat=:falazat,
          allapot=:allapot,
          statusz=:statusz,
          jeloles=:jeloles,
          modositva=NOW()
        WHERE id=:id";
$data['id'] = $id;

$st = $pdo->prepare($sql);
$st->execute($data);

// --- képfeltöltés kezelése ---
if (!empty($_FILES['kepek']['name'][0])) {
  $uploadDir = __DIR__ . '/../uploads/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
  }

  foreach ($_FILES['kepek']['tmp_name'] as $idx => $tmpName) {
    if (!is_uploaded_file($tmpName)) continue;

    $name = basename($_FILES['kepek']['name'][$idx]);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $newName = uniqid('img_').'.'.$ext;
    $destPath = $uploadDir . $newName;

    if (move_uploaded_file($tmpName, $destPath)) {
      $dbPath = 'uploads/'.$newName;

      // borítókép jelölés
      $isCover = (!empty($_POST['cover']) && (int)$_POST['cover'] === $idx) ? 1 : 0;

      // ha cover, előbb nullázd az összes cover-t
      if ($isCover) {
        $pdo->prepare("UPDATE ingatlan_kepek SET is_cover=0 WHERE ingatlan_id=?")->execute([$id]);
      }

      $pdo->prepare("INSERT INTO ingatlan_kepek (ingatlan_id, kep_url, is_cover) VALUES (?,?,?)")
          ->execute([$id, $dbPath, $isCover]);
    }
  }
}

// --- meglévő képek cover módosítása ---
if (isset($_POST['existing_cover'])) {
  $pdo->prepare("UPDATE ingatlan_kepek SET is_cover=0 WHERE ingatlan_id=?")->execute([$id]);
  $coverId = (int)$_POST['existing_cover'];
  $pdo->prepare("UPDATE ingatlan_kepek SET is_cover=1 WHERE id=? AND ingatlan_id=?")
      ->execute([$coverId, $id]);
}

// --- visszairányítás ---
$adminBase = rtrim(dirname($_SERVER['PHP_SELF']), '/'); // pl. /nido/admin
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$go        = $scheme . '://' . $host . $adminBase . '/ingatlanok.php?ok=1';

if (!headers_sent()) {
  header('Location: ' . $go, true, 303);
  exit;
} else {
  echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($go, ENT_QUOTES, 'UTF-8') . '">';
  echo '<script>location.href=' . json_encode($go) . ';</script>';
  exit;
}
