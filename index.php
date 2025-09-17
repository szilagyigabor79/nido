<?php
/* ====== Adatbázis kapcsolat ====== */
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'igngatlan_db';         // ha más, írd át
$DB_USER = 'nidoapp';
$DB_PASS = 'ValamiErősJelszó123!';

try {
  $pdo = new PDO(
    "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
    $DB_USER, $DB_PASS,
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
      SELECT ingatlan_id, MIN(id) AS first_id
      FROM ingatlan_kepek
      GROUP BY ingatlan_id
    ) x ON x.first_id = ik.id
  ) k ON k.ingatlan_id = i.id
  WHERE i.statusz = 'Aktív'
  ORDER BY i.letrehozva DESC
  LIMIT 60
";
$st = $pdo->query($sql);
$ingatlanok = $st->fetchAll();

/* ====== Segédek ====== */
function hu_price($n){ return number_format((float)$n, 0, ',', ' ') . ' Ft'; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Kártya komponens (badge: jeloles) */
function card_html($i){
  $img   = e($i['boritokep'] ?: '/uploads/placeholder.jpg');
  $title = trim(($i['varos'] ?? '') . ' — ' . ($i['utca'] ?? ''));
  $title = e($title ?: 'Ingatlan');
  $price = hu_price($i['ar_ft'] ?? 0);
  $link  = '/ingatlan.php?id='.(int)$i['id'];

  $badge = '';
  $jel   = $i['jeloles'] ?? '';
  if ($jel === 'Árcsökkent') {
    $badge = '<span class="absolute top-2 right-2 bg-red-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Árcsökkent</span>';
  } elseif ($jel === 'Új') {
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
    @keyframes scroll-left { from { transform: translateX(0) } to { transform: translateX(-50%) } }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 flex flex-col min-h-screen">


  <!-- FEJLÉC -->
  <header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
      <a href="www.nidoingatlan.hu"class="font-bold text-pink-900">nidoingatlan.hu</a>
      <nav class="text-sm flex gap-6">
        <a class="hover:text-pink-900" href="www.nidoingatlan.hu">Főoldal</a>
        <!-- <a class="hover:text-pink-900" href="/nido/rolam.html">Rólam</a> -->
        <a class="hover:text-pink-900" href="/nido/kereso.html">Kereső</a>
        <a class="hover:text-pink-900" href="https://startolj-ra.hu/"target="_blank" rel="noopener noreferrer">Otthon Start</a>
        <a class="hover:text-pink-900" href="https://www.mnb.hu/fogyasztovedelem/hitel-lizing/jelzalog-hitelek/csok-plusz-hitelprogram"target="_blank" rel="noopener noreferrer">CSOK +</a>
        </nav>
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

  <!-- (NINCS szekciócím) -->

  <!-- ÖSSZES AKTÍV INGATLAN – középre igazítva -->
  <section class="mt-6">
    <div class="marquee-outer overflow-hidden">
      <div class="marquee-track flex gap-6 justify-center" id="featuredTrack">
        <?php
        if (!empty($ingatlanok)) {
          foreach ($ingatlanok as $k) echo card_html($k);
        } else {
          // Ha nincs adat, tegyünk be 3 demót középre (nem animáljuk)
          $demo = [
            ['id'=>1,'varos'=>'Budapest','utca'=>'Andrássy út','ar_ft'=>89000000,'boritokep'=>'/uploads/demo1.jpg','jeloles'=>'Új'],
            ['id'=>2,'varos'=>'Győr','utca'=>'Baross út','ar_ft'=>59900000,'boritokep'=>'/uploads/demo2.jpg','jeloles'=>'Árcsökkent'],
            ['id'=>3,'varos'=>'Szeged','utca'=>'Tisza Lajos krt.','ar_ft'=>45900000,'boritokep'=>'/uploads/demo3.jpg','jeloles'=>''],
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

  <!-- LÁBLÉC – sötétszürke alap -->
  <footer class="mt-auto bg-gray-900 text-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-8 grid gap-3 text-sm sm:flex sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <!-- Telefon ikon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-2.05a1.5 1.5 0 0 0-1.264-1.482l-3.338-.557a1.5 1.5 0 0 0-1.286.43l-.97.97a12.035 12.035 0 0 1-5.385-5.385l.97-.97a1.5 1.5 0 0 0 .43-1.286l-.557-3.338A1.5 1.5 0 0 0 8.55 3.75H6.5A2.25 2.25 0 0 0 4.25 6v.75z"/>
        </svg>
        <a href="tel:+36204465216" class="text-base hover:text-white">+36 20 446 5216</a>
      </div>
      <div class="flex items-center gap-3">
        <!-- @ ikon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2C6.486 2 2 6.262 2 11.5S6.486 21 12 21c2.43 0 4.657-.816 6.33-2.19a1 1 0 1 0-1.286-1.536A8.022 8.022 0 0 1 12 19C7.589 19 4 15.865 4 11.5S7.589 4 12 4s8 3.135 8 7.5v.75c0 1.447-1.18 2.625-2.636 2.625-.868 0-1.636-.41-2.105-1.037A4.497 4.497 0 0 1 7.5 11.5a4.5 4.5 0 0 1 8.824-1.5 1 1 0 1 0 1.852-.74A6.5 6.5 0 1 0 18 15.75v.25c0 .241.195.437.436.437C19.228 16.437 20 15.664 20 14.75V11.5C20 6.262 17.514 2 12 2Zm0 6a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/>
        </svg>
        <a href="mailto:erika@nidoingatlan.hu" class="text-base hover:text-white">erika@nidoingatlan.hu</a>
      </div>
      <div class="text-gray-400">&copy; <?= date('Y') ?> Nido Ingatlan</div>
    </div>
  </footer>

  <!-- Csak akkor animálunk, ha tényleg túlcsordul -->
  <script>
    (function(){
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
        const dataCount = track.dataset.count ? parseInt(track.dataset.count,10) : cards.length;
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
      window.addEventListener('resize', () => { clearTimeout(window._rt); window._rt = setTimeout(update, 150); });
    })();
  </script>
</body>
</html>



