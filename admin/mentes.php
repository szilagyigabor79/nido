<?php
// admin/mentes.php
declare(strict_types=1);

// --- Fejlesztés közbeni hibakiírás (ha zavar, vedd ki) ---
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

// --- Adatbázis kapcsolat ---
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'igngatlan_db';     // nálad ez a jó
$DB_USER = 'nidoapp';
$DB_PASS = 'ValamiErősJelszó123!';

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  exit('Adatbázis hiba: '.htmlspecialchars($e->getMessage()));
}

// --- ENUM/SET engedélyezett értékek a sémád szerint ---
$OPT_TIPUS   = ['Lakás','Ház','Telek','Garázs','Üzlethelyiség','Tároló'];
$OPT_FALAZAT = ['Tégla','Beton','Ytong','Panel','Csúsztatott zsalus','Vegyes','Vályog'];
$OPT_ALLAPOT = ['Új','Újszerű','Felújított','Átlagos','Közepes','Felújítandó'];
$OPT_STATUSZ = ['Aktív','Foglalva','Eladva'];
$OPT_JELOLES = ['Új a kínálatban','Kiemelt','Árcsökkenés']; // SET

// --- Bejövő adatok ---
$tipus       = (string)($_POST['tipus']   ?? '');
$varos       = (string)($_POST['varos']   ?? '');
$utca        = (string)($_POST['utca']    ?? '');
$emelet      = ($_POST['emelet']      ?? '');         // lehet üres
$alapterulet = ($_POST['alapterulet'] ?? '');
$szobaszam   = ($_POST['szobaszam']   ?? '');
$felszoba    = ($_POST['felszoba']    ?? '');
$ar_ft       = ($_POST['ar_ft']       ?? '');
$leiras      = (string)($_POST['leiras']  ?? '');
$falazat     = (string)($_POST['falazat'] ?? '');
$allapot     = (string)($_POST['allapot'] ?? '');
$statusz     = (string)($_POST['statusz'] ?? 'Aktív');

// --- jelölés (SET) normalizálása ---
// az űrlap checkboxok miatt tömbként érkezik; fogadjuk a rövid alakot is (ha valahonnan úgy érkezne)
$rawJel = $_POST['jeloles'] ?? [];
if (!is_array($rawJel)) $rawJel = [$rawJel];
$mapShort = ['Új' => 'Új a kínálatban', 'Árcsökkent' => 'Árcsökkenés'];
$jelolesArr = [];
foreach ($rawJel as $v) {
  $v = trim((string)$v);
  if ($v === '') continue;
  if (isset($mapShort[$v])) $v = $mapShort[$v];
  if (in_array($v, $OPT_JELOLES, true)) $jelolesArr[] = $v; // whitelist
}
$jelolesArr = array_values(array_unique($jelolesArr));
$jeloles = implode(',', $jelolesArr); // DB SET formátum

// --- ENUM védelem ---
if (!in_array($tipus,   $OPT_TIPUS,   true)) $tipus   = $OPT_TIPUS[0];
if (!in_array($falazat, $OPT_FALAZAT, true)) $falazat = $OPT_FALAZAT[0];
if (!in_array($allapot, $OPT_ALLAPOT, true)) $allapot = $OPT_ALLAPOT[0];
if (!in_array($statusz, $OPT_STATUSZ, true)) $statusz = 'Aktív';

// --- Számmezők tisztítása ---
$emelet      = ($emelet      !== '' ? (string)$emelet : null);
$alapterulet = ($alapterulet !== '' ? (int)$alapterulet : null);
$szobaszam   = ($szobaszam   !== '' ? (float)$szobaszam : null);
$felszoba    = ($felszoba    !== '' ? (int)$felszoba : null);
$ar_ft       = ($ar_ft       !== '' ? (int)preg_replace('/\D+/', '', (string)$ar_ft) : null);

// --- Beszúrás az ingatlanok táblába ---
$ins = $pdo->prepare("
  INSERT INTO ingatlanok
    (tipus, varos, utca, emelet, alapterulet, szobaszam, felszoba, ar_ft, leiras, falazat, allapot, statusz, jeloles, letrehozva)
  VALUES
    (:tipus,:varos,:utca,:emelet,:alapterulet,:szobaszam,:felszoba,:ar_ft,:leiras,:falazat,:allapot,:statusz,:jeloles, NOW())
");
$ins->execute([
  ':tipus'        => $tipus,
  ':varos'        => $varos,
  ':utca'         => $utca,
  ':emelet'       => $emelet,
  ':alapterulet'  => $alapterulet,
  ':szobaszam'    => $szobaszam,
  ':felszoba'     => $felszoba,
  ':ar_ft'        => $ar_ft,
  ':leiras'       => $leiras,
  ':falazat'      => $falazat,
  ':allapot'      => $allapot,
  ':statusz'      => $statusz,
  ':jeloles'      => $jeloles,
]);
$ingatlanId = (int)$pdo->lastInsertId();

// --- Több kép feltöltése + borító kijelölése ---
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

$files = !empty($_FILES['kepek']) ? normalize_files_array($_FILES['kepek']) : [];
$coverIndex = isset($_POST['cover_index']) && $_POST['cover_index'] !== '' ? (int)$_POST['cover_index'] : 0;

$rootDir      = dirname(__DIR__);          // .../nido
$uploadsDirFs = $rootDir . '/uploads';     // fájlrendszer
if (!is_dir($uploadsDirFs)) { @mkdir($uploadsDirFs, 0775, true); }

$insertedIds = [];
if ($files) {
  $MAX = 8 * 1024 * 1024;
  $allowed = ['jpg','jpeg','png','webp'];
  $stmtImg = $pdo->prepare("INSERT INTO ingatlan_kepek (ingatlan_id, kep_url, is_cover) VALUES (:id, :url, :cover)");

  foreach ($files as $idx => $f) {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    if ($f['size'] > $MAX) continue;

    $orig = $f['name'] ?: 'kep';
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) $ext = 'jpg';

    // MIME check (ha elérhető)
    if (function_exists('finfo_open')) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($f['tmp_name']);
      if (!preg_match('~^image/(jpeg|png|webp)$~', (string)$mime)) continue;
    }

    $fileName = 'ingatlan_'.$ingatlanId.'_'.uniqid('', true).'.'.$ext;
    $destFs   = $uploadsDirFs.'/'.$fileName;
    $destUrl  = '/uploads/'.$fileName; // ezt tároljuk

    if (move_uploaded_file($f['tmp_name'], $destFs)) {
      $isCover = (int)($idx === $coverIndex);
      $stmtImg->execute([':id'=>$ingatlanId, ':url'=>$destUrl, ':cover'=>$isCover]);
      $insertedIds[] = (int)$pdo->lastInsertId();
    }
  }

  // --- Pontosan 1 borítót hagyjunk meg, ha lett legalább 1 kép ---
  if ($insertedIds) {
    // Van-e borító?
    $st = $pdo->prepare("SELECT COUNT(*) FROM ingatlan_kepek WHERE ingatlan_id = :id AND is_cover = 1");
    $st->execute([':id' => $ingatlanId]);
    $hasCover = (bool)$st->fetchColumn();

    // Ha nincs, az első frissen feltöltött legyen
    if (!$hasCover) {
      $coverId = $insertedIds[0];
    } else {
      // Ha több is lenne, válasszuk ki az elsőt
      $st2 = $pdo->prepare("
        SELECT id FROM ingatlan_kepek
        WHERE ingatlan_id = :id AND is_cover = 1
        ORDER BY id ASC LIMIT 1
      ");
      $st2->execute([':id' => $ingatlanId]);
      $coverId = (int)$st2->fetchColumn();
    }

    // Normalizálás: minden 0, kivéve a kiválasztott
    $pdo->prepare("UPDATE ingatlan_kepek SET is_cover = 0 WHERE ingatlan_id = :id")
        ->execute([':id' => $ingatlanId]);
    $pdo->prepare("UPDATE ingatlan_kepek SET is_cover = 1 WHERE id = :id")
        ->execute([':id' => $coverId]);
  }
}

// --- Visszairányítás az admin listára (robosztus) ---
if (file_exists(__DIR__.'/ingatlanok.php')) {
  header('Location: ingatlanok.php?ok=1', true, 303);
  exit;
}
header('Location: ../index.php?ok=1', true, 303);
exit;
