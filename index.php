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
    <img src="<?= $img ?>" alt="<?= $title ?>" class="w-full h-40 object-cover">
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
  <footer class="mt-auto bg-gray-900 text-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-8 grid gap-3 text-sm sm:flex sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <!-- Telefon ikon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-2.05a1.5 1.5 0 0 0-1.264-1.482l-3.338-.557a1.5 1.5 0 0 0-1.286.43l-.97.97a12.035 12.035 0 0 1-5.385-5.385l.97-.97a1.5 1.5 0 0 0 .43-1.286l-.557-3.338A1.5 1.5 0 0 0 8.55 3.75H6.5A2.25 2.25 0 0 0 4.25 6v.75z" />
        </svg>
        <a href="tel:+36204465216" class="text-base hover:text-white">+36 20 446 5216</a>
      </div>

      <div class="flex items-center gap-3">
        <!-- Email ikon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M3 6.75l9 6 9-6M3 6.75v10.5h18V6.75M3 6.75l9 6 9-6" />
        </svg>
        <a href="mailto:erika@nidoingatlan.hu" class="text-base hover:text-white">erika@nidoingatlan.hu</a>
      </div>


      <div class="flex items-center gap-3">
        <!-- Facebook ikon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-current" viewBox="0 0 24 24">
          <path d="M22.675 0h-21.35C.6 0 0 .6 0 1.325v21.351C0 23.4.6 24 1.325 24h11.49v-9.294H9.692v-3.622h3.123V8.413c0-3.1 1.894-4.788 4.66-4.788 1.325 0 2.464.099 2.797.143v3.24h-1.918c-1.504 0-1.794.715-1.794 1.763v2.313h3.587l-.467 3.622h-3.12V24h6.116C23.4 24 24 23.4 24 22.676V1.325C24 .6 23.4 0 22.675 0z" />
        </svg>
        <a href="https://www.facebook.com/profile.php?id=61581141817899" target="_blank" rel="noopener noreferrer" class="text-base hover:text-white">
          Facebook
        </a>
      </div>

  </footer>

  <!-- Csak akkor animálunk, ha tényleg túlcsordul -->
  <script>
    (function() {
      const outer = document.querySelector('.marquee-outer');
      const track = document.getElementById('featuredTrack');
      if (!outer || !track) return;

      const update = () => {
        const cards = Array.from(track.children);
        if (!cards.length) return;

        // Reset
        track.classList.remove('animate');
        outer.classList.remove('is-animated');
        track.classList.add('justify-center');

        // Távolítsuk el a korábbi duplikátumokat (ha voltak)
        const dataCount = track.dataset.count ? parseInt(track.dataset.count, 10) : cards.length;
        while (track.children.length > dataCount) track.removeChild(track.lastElementChild);
        track.dataset.count = dataCount;

        // Szükséges-e a mozgás?
        const needsScroll = track.scrollWidth > outer.clientWidth + 4;

        if (needsScroll) {
          // Duplázás a végtelenítéshez
          const originals = Array.from(track.children).slice(0, dataCount);
          originals.forEach(node => track.appendChild(node.cloneNode(true)));

          // Animáció bekapcsolása
          track.classList.remove('justify-center');
          track.classList.add('animate');
          outer.classList.add('is-animated');
        }
      };

      window.addEventListener('load', update);
      window.addEventListener('resize', () => {
        clearTimeout(window._rt);
        window._rt = setTimeout(update, 150);
      });
    })();
  </script>
</body>

</html>