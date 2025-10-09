<?php
/* ====== Adatbázis kapcsolat ====== */
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'nidoinga_nido';
$DB_USER = 'nidoinga_admin';
$DB_PASS = '1_kafferBIValy13';

try {
  $pdo = new PDO(
    "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  echo "Adatbázis hiba: " . htmlspecialchars($e->getMessage());
  exit;
}

/* ====== KIEMELT / ÚJ ingatlanok + borítókép ====== */
$sql = "
  SELECT 
    i.id,
    i.varos,
    i.utca,
    i.ar_ft,
    i.statusz,
    i.jeloles,
    i.letrehozva,
    k.kep_url AS boritokep
  FROM ingatlanok i
  LEFT JOIN (
    SELECT ik.ingatlan_id, ik.kep_url
    FROM ingatlan_kepek ik
    JOIN (
      SELECT ingatlan_id,
             COALESCE(MAX(CASE WHEN is_cover=1 THEN id END), MIN(id)) AS chosen_id
      FROM ingatlan_kepek
      GROUP BY ingatlan_id
    ) x ON x.chosen_id = ik.id
  ) k ON k.ingatlan_id = i.id
  WHERE i.statusz = 'Aktív'
    AND (
      FIND_IN_SET('Kiemelt', i.jeloles)
      OR FIND_IN_SET('Új a kínálatban', i.jeloles)
    )
  ORDER BY i.letrehozva DESC
  LIMIT 60
";
$st = $pdo->query($sql);
$ingatlanok = $st->fetchAll();

/* ====== Segédek ====== */
function hu_price($n){ return number_format((float)$n, 0, ',', ' ') . ' Ft'; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function base_url_prefix(): string { return rtrim(dirname($_SERVER['PHP_SELF']), '/'); }
function asset_url(string $path): string {
  $path = ltrim($path, '/'); // /uploads/.. -> uploads/..
  return base_url_prefix() . '/' . $path;
}

/* ====== KÁRTYA KOMPONENS (ÚJ / ÁRCSÖKKENÉS / ELADVA) ====== */
function card_html($i){
  $raw   = $i['boritokep'] ?: 'uploads/placeholder.jpg';
  $img   = e(asset_url($raw));
  $title = e(trim(($i['varos'] ?? '') . ' — ' . ($i['utca'] ?? '')) ?: 'Ingatlan');
  $price = hu_price($i['ar_ft'] ?? 0);
  $link  = 'ingatlan.php?id=' . (int)$i['id'];

  $statusz = (string)($i['statusz'] ?? '');
  $tags    = array_filter(array_map('trim', explode(',', (string)($i['jeloles'] ?? ''))));

  // ----- BADGE-ek -----
  $badgeHtml = '';
  if ($statusz === 'Eladva') {
    // Eladva mindent felülír – adjunk z-indexet, hogy a kép ne takarja
    $badgeHtml = '<span class="absolute top-2 right-2 z-20 bg-gray-800 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Eladva</span>';
  } else {
    $badges = [];
    if (in_array('Új a kínálatban', $tags, true)) {
      $badges[] = '<span class="bg-green-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Új</span>';
    }
    if (in_array('Árcsökkenés', $tags, true)) {
      $badges[] = '<span class="bg-red-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Árcsökkenés</span>';
    }
    if ($badges) {
      // z-index a konténeren
      $badgeHtml = '<div class="absolute top-2 right-2 z-20 flex flex-col items-end gap-1">'.implode('', $badges).'</div>';
    }
  }

  // Elszürkítés csak eladott állapotban
  $imgClasses = 'w-full h-40 object-cover' . ($statusz === 'Eladva' ? ' grayscale opacity-60' : '');

  ob_start(); ?>
  <article class="relative min-w-[280px] w-[280px] bg-white rounded-2xl shadow-2xl hover:shadow-[0_25px_50px_rgba(0,0,0,0.25)] transition overflow-hidden">
    <?= $badgeHtml ?>
    <img src="<?= $img ?>" alt="<?= $title ?>" class="<?= $imgClasses ?>">
    <div class="p-4">
      <h3 class="text-base font-semibold line-clamp-2"><?= $title ?></h3>
      <p class="text-pink-900 font-bold mt-1"><?= $price ?></p>
      <a href="<?= e($link) ?>" class="mt-3 inline-block bg-pink-900 text-white px-4 py-2 rounded-lg hover:bg-pink-950">
        Részletek
      </a>
    </div>
  </article>
<?php return ob_get_clean();
}

?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Nido Ingatlan</title>
  <meta name="description" content="Nido Ingatlan – ingatlan eladás apró betűs rész nélkül.">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .marquee-outer.is-animated{
      mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
      -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
    }
    .marquee-track.animate{ animation: scroll-left 35s linear infinite; will-change: transform; }
    @keyframes scroll-left{ from{transform:translateX(0)} to{transform:translateX(-50%)} }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 flex flex-col min-h-screen">

  <!-- FEJLÉC -->
  <header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
      <a href="/index.php" class="font-bold text-pink-900 text-lg sm:text-xl">nidoingatlan.hu</a>
      <nav class="text-sm flex gap-6">
        <a class="hover:text-pink-900" href="/index.php">Főoldal</a>
        <a class="hover:text-pink-900" href="/kereso.php">Kereső</a>
        <a class="hover:text-pink-900" href="https://startolj-ra.hu/" target="_blank" rel="noopener noreferrer">Otthon Start</a>
        <a class="hover:text-pink-900" href="https://www.mnb.hu/fogyasztovedelem/hitel-lizing/jelzalog-hitelek/csok-plusz-hitelprogram" target="_blank" rel="noopener noreferrer">CSOK +</a>
      </nav>
    </div>
  </header>

  <!-- BRAND BLOKK -->
  <section class="max-w-6xl mx-auto px-4 mt-10 text-center select-none">
    <h1 class="font-extrabold tracking-tight uppercase text-pink-900 text-3xl sm:text-4xl">
      Nido Ingatlan
    </h1>
    <p class="mt-1 text-pink-900/90 text-sm sm:text-base tracking-wide">
      ingatlan eladás apró betűs rész nélkül
    </p>
  </section>

  <!-- KIEMELT/ÚJ INGATLANOK vagy PROMÓKÁRTYA -->
  <section class="mt-10 mb-20">
    <div class="marquee-outer overflow-hidden">
      <div class="marquee-track flex gap-10 justify-center flex-wrap" id="featuredTrack">
        <?php
        if (!empty($ingatlanok)) {
          foreach ($ingatlanok as $k) echo card_html($k);
        } else {
          // PROMÓKÁRTYA – ha nincs kiemelt / új
          echo '
          <article class="relative w-full max-w-[500px] bg-pink-100 rounded-3xl shadow-2xl hover:shadow-[0_30px_60px_rgba(0,0,0,0.3)] p-10 text-center mx-auto">
            <div class="text-6xl mb-6 text-pink-700">🏢</div>
            <h2 class="text-3xl font-extrabold text-green-900 mb-3">NIDO INGATLAN</h2>
            <p class="text-green-900 font-semibold leading-snug text-lg mb-4">
              OKTÓBER VÉGÉIG MEGKÖTÖTT<br>
              SZERZŐDÉSEKRE<br>
              EXTRA KEDVEZMÉNY!
            </p>
            <p class="text-green-900 font-bold text-xl mb-2">+36 20 446 5216</p>
            <p>
              <a href="mailto:erika@nidoingatlan.hu" class="underline font-semibold text-green-900 hover:text-green-700 text-lg">
                erika@nidoingatlan.hu
              </a>
            </p>
          </article>';
        }
        ?>
      </div>
    </div>
  </section>

  <!-- LÁBLÉC -->
  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
