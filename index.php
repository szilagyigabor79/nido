<?php
/* ====== Adatbázis kapcsolat ====== */
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'igngatlan_db';         // HA nálad tényleg "igngatlan_db", írd vissza arra
$DB_USER = 'nidoapp';
$DB_PASS = 'ValamiErősJelszó123!';

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

/* ====== Minden AKTÍV ingatlan + borítókép (első kép) ====== */
$sql = "
  SELECT 
    i.id,
    i.varos,
    i.utca,
    i.ar_ft,
    i.statusz,
    i.jeloles,       -- csak megjelenítéshez (badge)
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
  ORDER BY i.letrehozva DESC
  LIMIT 60
";
$st = $pdo->query($sql);
$ingatlanok = $st->fetchAll();

/* ====== Segédek ====== */
function hu_price($n)
{
  return number_format((float)$n, 0, ',', ' ') . ' Ft';
}
function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function base_url_prefix(): string
{
  return rtrim(dirname($_SERVER['PHP_SELF']), '/');
}
function asset_url(string $path): string
{
  $path = ltrim($path, '/'); // /uploads/.. -> uploads/..
  return base_url_prefix() . '/' . $path;
}


/* Kártya komponens (badge: jeloles) */
function card_html($i)
{
  $raw   = $i['boritokep'] ?: 'uploads/placeholder.jpg';
  $img   = e(asset_url($raw));

  $title = trim(($i['varos'] ?? '') . ' — ' . ($i['utca'] ?? ''));
  $title = e($title ?: 'Ingatlan');
  $price = hu_price($i['ar_ft'] ?? 0);
  $link  = 'ingatlan.php?id=' . (int)$i['id']; // relatív link is elég

  // BADGE – a DB SET mező (jeloles) alapján
  $badge = '';
  $tags  = array_filter(array_map('trim', explode(',', (string)($i['jeloles'] ?? ''))));

  if (in_array('Kiemelt', $tags, true)) {
    $badge = '<span class="absolute top-2 right-2 bg-purple-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Kiemelt</span>';
  } elseif (in_array('Árcsökkenés', $tags, true)) {
    $badge = '<span class="absolute top-2 right-2 bg-red-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Árcsökkent</span>';
  } elseif (in_array('Új a kínálatban', $tags, true)) {
    $badge = '<span class="absolute top-2 right-2 bg-green-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Új</span>';
  }


  ob_start(); ?>
  <article class="relative min-w-[280px] w-[280px] bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden">
    <?= $badge ?>
    <img
      src="<?= $img ?>"
      alt="<?= $title ?>"
      class="w-full h-40 object-cover <?= ($i['statusz'] !== 'Aktív') ? 'grayscale opacity-60' : '' ?>">


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
    /* Csak akkor legyen maszk és animáció, ha tényleg túlcsordul */
    .marquee-outer.is-animated {
      mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
      -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
    }

    .marquee-track.animate {
      animation: scroll-left 35s linear infinite;
      will-change: transform;
    }

    @keyframes scroll-left {
      from {
        transform: translateX(0)
      }

      to {
        transform: translateX(-50%)
      }
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-900 flex flex-col min-h-screen">

  <!-- FEJLÉC -->
  <header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
      <a href="/nido/index.php" class="font-bold text-pink-900">nidoingatlan.hu</a>
      <nav class="text-sm flex gap-6">
        <a class="hover:text-pink-900" href="/nido/index.php">Főoldal</a>
        <!-- <a class="hover:text-pink-900" href="/nido/rolam.html">Rólam</a> -->
        <a class="hover:text-pink-900" href="/nido/kereso.php">Kereső</a>
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

  <!-- ÖSSZES AKTÍV INGATLAN – középre igazítva -->
  <section class="mt-6">
    <div class="marquee-outer overflow-hidden">
      <div class="marquee-track flex gap-6 justify-center" id="featuredTrack">
        <?php
        if (!empty($ingatlanok)) {
          foreach ($ingatlanok as $k) echo card_html($k);
        } else {
          // Ha nincs adat, demo
          $demo = [
            ['id' => 1, 'varos' => 'Budapest', 'utca' => 'Andrássy út', 'ar_ft' => 89000000, 'boritokep' => '/uploads/demo1.jpg', 'jeloles' => 'Új'],
            ['id' => 2, 'varos' => 'Győr', 'utca' => 'Baross út', 'ar_ft' => 59900000, 'boritokep' => '/uploads/demo2.jpg', 'jeloles' => 'Árcsökkent'],
            ['id' => 3, 'varos' => 'Szeged', 'utca' => 'Tisza Lajos krt.', 'ar_ft' => 45900000, 'boritokep' => '/uploads/demo3.jpg', 'jeloles' => ''],
          ];
          foreach ($demo as $k) echo card_html($k);
        }
        ?>
      </div>
    </div>

    <?php if (empty($ingatlanok)): ?>
      <p class="text-center text-sm text-gray-500 mt-4">
        Jelenleg nincs aktív ingatlan az adatbázisban — demó látható.
      </p>
    <?php endif; ?>
  </section>

  <!-- LÁBLÉC – sötétszürke alap, mindig alul -->

  <<?php include __DIR__ . '/footer.php'; ?>




    </script>
</body>

</html>