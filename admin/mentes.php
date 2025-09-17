<?php
require __DIR__ . '/../config.php';

$id = (int)($_POST['id'] ?? 0);

/* Bejövő mezők */
$mezok = ['tipus','varos','utca','emelet','alapterulet','szobaszam','felszoba','ar_ft','leiras','falazat','allapot','statusz','jeloles','boritokep'];
$d = [];
foreach ($mezok as $m) { $d[$m] = trim($_POST[$m] ?? ''); }

/* Borítókép fájlfeltöltés (ha van) */
if (!empty($_FILES['boritokep_file']['name']) && $_FILES['boritokep_file']['error'] === UPLOAD_ERR_OK) {
  $up = save_image($_FILES['boritokep_file']);
  if ($up) $d['boritokep'] = $up; // pl. uploads/abc123.jpg
}

/* Mentés */
if ($id) {
  $sql = "UPDATE ingatlanok
          SET tipus=?, varos=?, utca=?, emelet=?, alapterulet=?, szobaszam=?, felszoba=?, ar_ft=?, leiras=?, falazat=?, allapot=?, statusz=?, jeloles=?, boritokep=?, modositva=NOW()
          WHERE id=?";
  $vals = [ $d['tipus'],$d['varos'],$d['utca'],$d['emelet'],$d['alapterulet'],$d['szobaszam'],$d['felszoba'],$d['ar_ft'],$d['leiras'],$d['falazat'],$d['allapot'],$d['statusz'],$d['jeloles'],$d['boritokep'],$id ];
  $pdo->prepare($sql)->execute($vals);
} else {
  $sql = "INSERT INTO ingatlanok
          (tipus,varos,utca,emelet,alapterulet,szobaszam,felszoba,ar_ft,leiras,falazat,allapot,statusz,jeloles,boritokep,letrehozva)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
  $vals = [ $d['tipus'],$d['varos'],$d['utca'],$d['emelet'],$d['alapterulet'],$d['szobaszam'],$d['felszoba'],$d['ar_ft'],$d['leiras'],$d['falazat'],$d['allapot'],$d['statusz'],$d['jeloles'],$d['boritokep'] ];
  $pdo->prepare($sql)->execute($vals);
  $id = (int)$pdo->lastInsertId();
}

/* Galéria képek feltöltése */
if (!empty($_FILES['kepek']['name'][0])) {
  if (!is_dir(__DIR__ . '/../uploads')) mkdir(__DIR__ . '/../uploads', 0775, true);
  foreach ($_FILES['kepek']['name'] as $i => $n) {
    if ($_FILES['kepek']['error'][$i] !== UPLOAD_ERR_OK) continue;
    $file = [
      'name'     => $_FILES['kepek']['name'][$i],
      'type'     => $_FILES['kepek']['type'][$i],
      'tmp_name' => $_FILES['kepek']['tmp_name'][$i],
      'error'    => $_FILES['kepek']['error'][$i],
      'size'     => $_FILES['kepek']['size'][$i]
    ];
    $rel = save_image($file);
    if ($rel) {
      $pdo->prepare("INSERT INTO ingatlan_kepek (ingatlan_id, kep_url) VALUES (?,?)")
          ->execute([$id, $rel]);
    }
  }
}

header('Location: szerkesztes.php?id=' . $id);
exit;

/* ===== Helpers ===== */

function save_image(array $f): ?string {
  $allow = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if (!isset($allow[$f['type']])) return null;
  if ($f['size'] > 8*1024*1024) return null; // max 8 MB
  $ext = $allow[$f['type']];
  $name = bin2hex(random_bytes(8)) . '.' . $ext;
  $dir = __DIR__ . '/../uploads';
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $dest = $dir . '/' . $name;
  if (move_uploaded_file($f['tmp_name'], $dest)) {
    return 'uploads/' . $name; // relatív út a webhez
  }
  return null;
}
