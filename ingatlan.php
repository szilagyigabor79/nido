<?php
// ingatlan.php – részletek oldal

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ===== DB ===== */
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'igngatlan_db';
$DB_USER = 'nidoapp';
$DB_PASS = 'ValamiErősJelszó123!';
try {
  $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  exit('Adatbázis hiba: ' . htmlspecialchars($e->getMessage()));
}

/* ===== Segédek ===== */
function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function hu_price($n)
{
  return $n !== null ? number_format((float)$n, 0, ',', ' ') . ' Ft' : '—';
}
function base_url_prefix()
{
  return rtrim(dirname($_SERVER['PHP_SELF']), '/');
}
function asset_url($path)
{
  $path = ltrim((string)$path, '/');
  return base_url_prefix() . '/' . $path;
}

/* ===== Param ===== */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  exit('Hiányzó azonosító.');
}

/* ===== Rekord + borítókép ===== */
$sql = "
SELECT i.*, k.kep_url AS boritokep
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
WHERE i.id = :id
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $id]);
$rec = $st->fetch();
if (!$rec) {
  http_response_code(404);
  exit('A kért ingatlan nem található.');
}

/* ===== Képek a galériához ===== */
$gs = $pdo->prepare("SELECT id, kep_url, is_cover FROM ingatlan_kepek WHERE ingatlan_id=:id ORDER BY is_cover DESC, id ASC");
$gs->execute([':id' => $id]);
$kepek = $gs->fetchAll();
?>
<!doctype html>
<html lang="hu">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e(($rec['varos'] ?: 'Ingatlan') . ' – Nido Ingatlan') ?></title>
  <meta name="description" content="Ingatlan részletei – Nido Ingatlan">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">

  <header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
      <a href="<?= e(base_url_prefix()) ?>/index.php" class="font-bold text-pink-900">nidoingatlan.hu</a>
      <nav class="text-sm flex gap-6">
        <a class="hover:text-pink-900" href="<?= e(base_url_prefix()) ?>/index.php">Főoldal</a>
        <a class="hover:text-pink-900" href="<?= e(base_url_prefix()) ?>/kereso.php">Kereső</a>
        <a class="hover:text-pink-900" href="https://startolj-ra.hu/" target="_blank" rel="noopener">Otthon Start</a>
        <a class="hover:text-pink-900" href="https://www.mnb.hu/fogyasztovedelem/hitel-lizing/jelzalog-hitelek/csok-plusz-hitelprogram" target="_blank" rel="noopener">CSOK +</a>
      </nav>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-6 w-full">
    <a href="<?= e(base_url_prefix()) ?>/index.php" class="text-sm text-pink-900 hover:underline">&larr; Vissza</a>

    <div class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Galéria -->
      <section class="lg:col-span-2">
        <?php
        $hero = $rec['boritokep'] ?: ($kepek[0]['kep_url'] ?? 'uploads/placeholder.jpg');
        $hero = asset_url($hero);
        ?>
        <div class="bg-white rounded-2xl shadow overflow-hidden">
          <img id="hero" src="<?= e($hero) ?>" alt="" class="w-full aspect-video object-cover rounded">


          <?php if (count($kepek) > 1): ?>
            <div class="p-3 grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
              <?php foreach ($kepek as $k): ?>
                <?php $thumb = asset_url($k['kep_url']); ?>
                <img
                  src="<?= e($thumb) ?>"
                  data-full="<?= e($thumb) ?>"
                  alt=""
                  class="thumb w-full h-20 object-cover rounded cursor-pointer border border-transparent"
                  onclick="selectThumb(this)">
              <?php endforeach; ?>

            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Adatok -->
      <section class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow p-5">
          <h1 class="text-xl font-bold">
            <?= e(($rec['varos'] ?: '') . ($rec['utca'] ? ' — ' . $rec['utca'] : '') ?: 'Ingatlan') ?>
          </h1>
          <div class="mt-2 text-pink-900 text-xl font-extrabold"><?= hu_price($rec['ar_ft']) ?></div>

          <?php
          $tags = array_filter(array_map('trim', explode(',', (string)($rec['jeloles'] ?? ''))));
          ?>
          <?php if ($tags): ?>
            <div class="mt-3 flex flex-wrap gap-2">
              <?php if (in_array('Kiemelt', $tags, true)): ?>
                <span class="px-2 py-1 text-xs rounded-full bg-purple-700 text-white">Kiemelt</span>
              <?php endif; ?>
              <?php if (in_array('Árcsökkenés', $tags, true)): ?>
                <span class="px-2 py-1 text-xs rounded-full bg-red-700 text-white">Árcsökkent</span>
              <?php endif; ?>
              <?php if (in_array('Új a kínálatban', $tags, true)): ?>
                <span class="px-2 py-1 text-xs rounded-full bg-green-700 text-white">Új</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt class="text-gray-500">Típus</dt>
            <dd><?= e($rec['tipus'] ?? '—') ?></dd>
            <dt class="text-gray-500">Alapterület</dt>
            <dd><?= e($rec['alapterulet'] ? $rec['alapterulet'] . ' m²' : '—') ?></dd>
            <dt class="text-gray-500">Szobaszám</dt>
            <dd><?= e($rec['szobaszam'] ?? '—') ?><?= $rec['felszoba'] ? ' + ' . (int)$rec['felszoba'] . ' fél' : '' ?></dd>
            <dt class="text-gray-500">Emelet</dt>
            <dd><?= e($rec['emelet'] ?? '—') ?></dd>
            <dt class="text-gray-500">Falazat</dt>
            <dd><?= e($rec['falazat'] ?? '—') ?></dd>
            <dt class="text-gray-500">Állapot</dt>
            <dd><?= e($rec['allapot'] ?? '—') ?></dd>
            <?php /* <dt class="text-gray-500">Státusz</dt>
            <dd><?= e($rec['statusz'] ?? '—') ?></dd>*/ ?>
          </dl>

          <?php if (!empty($rec['leiras'])): ?>
            <div class="mt-4 whitespace-pre-line text-sm leading-6"><?= nl2br(e($rec['leiras'])) ?></div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <?php include __DIR__ . '/footer.php'; ?>

  <script>
    // Váltás bélyegre kattintva
    function selectThumb(el) {
      const hero = document.getElementById('hero');
      if (!hero) return;
      const url = el.dataset.full || el.src;
      hero.src = url;

      // aktív jelölés
      document.querySelectorAll('.thumb').forEach(t => t.classList.remove('ring-2', 'ring-pink-600'));
      el.classList.add('ring-2', 'ring-pink-600');
    }

    // Alapból jelöld ki az első bélyeget (ha van)
    window.addEventListener('load', () => {
      const first = document.querySelector('.thumb');
      if (first) selectThumb(first);
    });

    // Billentyűzettel is lehessen lépkedni (← →)
    document.addEventListener('keydown', (e) => {
      if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
      const thumbs = Array.from(document.querySelectorAll('.thumb'));
      if (!thumbs.length) return;

      const heroSrc = document.getElementById('hero')?.src || '';
      let idx = thumbs.findIndex(t => (t.dataset.full || t.src) === heroSrc);
      if (idx === -1) idx = 0;

      if (e.key === 'ArrowLeft') idx = (idx - 1 + thumbs.length) % thumbs.length;
      if (e.key === 'ArrowRight') idx = (idx + 1) % thumbs.length;

      selectThumb(thumbs[idx]);
    });
  </script>
  
</body>

</html>