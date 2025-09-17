<?php
// admin/mentes.php
declare(strict_types=1);

$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'igngatlan_db';
$DB_USER = 'nidoapp';
$DB_PASS = 'ValamiErősJelszó123!';

try {
  $pdo = new PDO(
    "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  exit("Adatbázis hiba: " . htmlspecialchars($e->getMessage()));
}

// --- 1) Bejövő adatok (ne legyen boritokep mező!)
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
$jeloles     = $_POST['jeloles']      ?? ''; // 'Új' / 'Árcsökkent' / '' stb.

// --- 2) INSERT az ingatlanok táblába (NINCS boritokep oszlop!)
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

// --- 3) (Opcionális) KÉP FELTÖLTÉS -> ingatlan_kepek (ingatlan_id, kep_url)
if (!empty($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
  $uploadsDirFs = realpath(__DIR__ . '/../') . '/uploads';  // FS útvonal
  if (!is_dir($uploadsDirFs)) { mkdir($uploadsDirFs, 0775, true); }

  $origName = $_FILES['foto']['name'] ?? 'kep';
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  if ($ext === '') { $ext = 'jpg'; } // fallback

  $safeExt = preg_replace('/[^a-z0-9]/', '', $ext);
  $fileName = 'ingatlan_' . $ingatlanId . '_' . time() . '.' . $safeExt;
  $destFs   = $uploadsDirFs . '/' . $fileName;
  $destUrl  = '/uploads/' . $fileName; // ezt írjuk az adatbázisba

  if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destFs)) {
    // Nem sikerült a feltöltés – nem dőlünk el, csak kihagyjuk a képet
    // (Ha akarsz, itt feldobhatsz session flash üzenetet.)
  } else {
    $stmtImg = $pdo->prepare("INSERT INTO ingatlan_kepek (ingatlan_id, kep_url) VALUES (:id, :url)");
    $stmtImg->execute([':id' => $ingatlanId, ':url' => $destUrl]);
  }
}

// --- 4) Visszairányítás
$base = rtrim(dirname($_SERVER['PHP_SELF']), '/'); // pl. /nido/admin
header('Location: ' . $base . '/index.php?ok=1');

// --- 4) Visszairányítás (robusztus)
$hereDirFs   = __DIR__;                          // .../nido/admin
$hereIndexFs = $hereDirFs . '/index.php';
$hereIndex   = 'index.php?ok=1';

if (file_exists($hereIndexFs)) {
  // admin/index.php létezik -> oda megyünk
  header('Location: ' . $hereIndex, true, 303);
  exit;
}

// ha admin/index.php nincs, menjünk a szülő mappába (főoldal)
$parentIndexFs = dirname($hereDirFs) . '/index.php';
$parentIndex   = '../index.php?ok=1';

if (file_exists($parentIndexFs)) {
  header('Location: ' . $parentIndex, true, 303);
  exit;
}

// végső fallback: ha volt referer, menjünk vissza oda
if (!empty($_SERVER['HTTP_REFERER'])) {
  header('Location: ' . $_SERVER['HTTP_REFERER'], true, 303);
  exit;
}

// ha minden kötél szakad, írjunk ki egy linket (nem szép, de működik)
echo '<p>Mentés sikeres. <a href="../index.php">Vissza az adminhoz</a> | <a href="../">Főoldal</a></p>';
exit;


