<?php
// admin/mentes.php
declare(strict_types=1);

/* DB kapcsolat */
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'igngatlan_db';   // ha más, írd át
$DB_USER = 'nidoapp';
$DB_PASS = 'ValamiErősJelszó123!';

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  exit("Adatbázis hiba: " . htmlspecialchars($e->getMessage()));
}

/* Bejövő adatok */
$tipus       = $_POST['tipus']        ?? null;
$varos       = $_POST['varos']        ?? null;
$utca        = $_POST['utca']         ?? null;
$emelet      = $_POST['emelet']       ?? null;
$alapterulet = $_POST['alapterulet']  ?? null;
$szobaszam   = $_POST['szobaszam']    ?? null;
$felszoba    = $_POST['felszoba']     ?? null;
$ar_ft       = $_POST['ar_ft']        ?? null;
$leiras      = $_POST['leiras']       ?? null;
$falazat     = $_POST['falazat']      ?? null;
$allapot     = $_POST['allapot']      ?? null;
$statusz     = $_POST['statusz']      ?? 'Aktív';
$jeloles     = $_POST['jeloles']      ?? '';
$coverIndex  = isset($_POST['cover_index']) ? (int)$_POST['cover_index'] : 0;

/* INSERT ingatlanok (csak meglévő oszlopok!) */
$sql = "
  INSERT INTO ingatlanok
    (tipus, varos, utca, emelet, alapterulet, szobaszam, felszoba, ar_ft, leiras, falazat, allapot, statusz, jeloles, letrehozva)
  VALUES
    (:tipus, :varos, :utca, :emelet, :alapterulet, :szobaszam, :felszoba, :ar_ft, :leiras, :falazat, :allapot, :statusz, :jeloles, NOW())
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':tipus'        => $tipus,
  ':varos'        => $varos,
  ':utca'         => $utca,
  ':emelet'       => $emelet !== '' ? $emelet : null,
  ':alapterulet'  => $alapterulet !== '' ? (int)$alapterulet : null,
  ':szobaszam'    => $szobaszam !== '' ? (int)$szobaszam : null,
  ':felszoba'     => $felszoba !== '' ? (int)$felszoba : null,
  ':ar_ft'        => $ar_ft !== '' ? (int)preg_replace('/\D+/', '', (string)$ar_ft) : null,
  ':leiras'       => $leiras,
  ':falazat'      => $falazat,
  ':allapot'      => $allapot,
  ':statusz'      => $statusz,
  ':jeloles'      => $jeloles,
]);

$ingatlanId = (int)$pdo->lastInsertId();

/* Több fájl normalizálása */
function normalize_files_array(array $files): array {
  $out = [];
  if (!isset($files['name']) || !is_array($files['name'])) return $out;
  $n = count($files['name']);
  for ($i=0; $i<$n; $i++) {
    $out[] = [
      'name'     => $files['name'][$i] ?? '',
      'type'     => $files['type'][$i] ?? '',
      'tmp_name' => $files['tmp_name'][$i] ?? '',
      'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
      'size'     => $files['size'][$i] ?? 0,
    ];
  }
  return $out;
}

/* Képek mentése -> ingatlan_kepek (ingatlan_id, kep_url, is_cover) */
$files = !empty($_FILES['kepek']) ? normalize_files_array($_FILES['kepek']) : [];
$insertedAny = false;
$coverInserted = false;

if ($files) {
  $uploadsDirFs = realpath(__DIR__ . '/../') . '/uploads';
  if (!is_dir($uploadsDirFs)) { @mkdir($uploadsDirFs, 0775, true); }

  $MAX = 8 * 1024 * 1024;
  $allowed = ['jpg','jpeg','png','webp'];

  $stmtImg = $pdo->prepare("INSERT INTO ingatlan_kepek (ingatlan_id, kep_url, is_cover) VALUES (:id, :url, :cover)");

  foreach ($files as $idx => $f) {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    if ($f['size'] > $MAX) continue;

    $orig = $f['name'] ?: 'kep';
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) $ext = 'jpg';

    // gyors MIME-ellenőrzés (ha elérhető)
    if (function_exists('finfo_open')) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($f['tmp_name']);
      if (!preg_match('~^image/(jpeg|png|webp)$~', (string)$mime)) continue;
    }

    $fileName = 'ingatlan_' . $ingatlanId . '_' . uniqid('', true) . '.' . $ext;
    $destFs   = $uploadsDirFs . '/' . $fileName;
    $destUrl  = '/uploads/' . $fileName;

    if (move_uploaded_file($f['tmp_name'], $destFs)) {
      $isCover = (int)($idx === $coverIndex);
      if ($isCover) $coverInserted = true;
      $stmtImg->execute([':id'=>$ingatlanId, ':url'=>$destUrl, ':cover'=>$isCover]);
      $insertedAny = true;
    }
  }

  // ha nem sikerült a kijelölt borítót beállítani, de van feltöltött kép, tegyük az elsőt borítónak
  if ($insertedAny && !$coverInserted) {
    // nullázunk, majd legkisebb id -> is_cover=1
    $pdo->prepare("UPDATE ingatlan_kepek SET is_cover = 0 WHERE ingatlan_id = :id")
        ->execute([':id' => $ingatlanId]);
    $pdo->prepare("
      UPDATE ingatlan_kepek
      SET is_cover = 1
      WHERE id = (
        SELECT id FROM (
          SELECT id FROM ingatlan_kepek
          WHERE ingatlan_id = :id
          ORDER BY id ASC
          LIMIT 1
        ) t
      )
    ")->execute([':id' => $ingatlanId]);
  } elseif ($coverInserted) {
    // biztosítsuk, hogy csak egy borító legyen
    $pdo->prepare("UPDATE ingatlan_kepek SET is_cover = 0 WHERE ingatlan_id = :id AND is_cover = 1")
        ->execute([':id' => $ingatlanId]);
    $pdo->prepare("
      UPDATE ingatlan_kepek
      SET is_cover = 1
      WHERE ingatlan_id = :id
      ORDER BY id ASC
      LIMIT 1
    ")->execute([':id' => $ingatlanId]);
  }
}

/* Robusztus visszairányítás */
$hereDirFs   = __DIR__;
$hereIndexFs = $hereDirFs . '/index.php';
if (file_exists($hereIndexFs)) {
  header('Location: index.php?ok=1', true, 303);
  exit;
}
if (file_exists(dirname($hereDirFs) . '/index.php')) {
  header('Location: ../index.php?ok=1', true, 303);
  exit;
}
echo '<p>Mentés sikeres. <a href="../index.php">Vissza</a></p>';
