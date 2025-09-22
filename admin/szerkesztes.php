<?php
// admin/szerkesztes.php
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/../config.php';

// ===== Opciók a DB sémád alapján =====
$OPT_TIPUS   = ['Lakás','Ház','Telek','Garázs','Üzlethelyiség','Tároló'];
$OPT_FALAZAT = ['Tégla','Beton','Ytong','Panel','Csúsztatott zsalus','Vegyes','Vályog'];
$OPT_ALLAPOT = ['Új','Újszerű','Felújított','Átlagos','Közepes','Felújítandó'];
$OPT_STATUSZ = ['Aktív','Foglalva','Eladva'];
$OPT_JELOLES = ['Új a kínálatban','Kiemelt','Árcsökkenés']; // SET (több jelölés engedett)

// ===== Rekord betöltése (ha van id) =====
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Alapértékek új rekordhoz
$data = [
  'tipus' => $OPT_TIPUS[0],
  'varos' => '', 'utca' => '', 'emelet' => '',
  'alapterulet' => '', 'szobaszam' => '', 'felszoba' => '',
  'ar_ft' => '', 'leiras' => '',
  'falazat' => $OPT_FALAZAT[0],
  'allapot' => $OPT_ALLAPOT[0],
  'statusz' => 'Aktív',
  'jeloles' => '' // SET mező: "érték1,érték2"
];
$kepek = [];

if ($id > 0) {
  $st = $pdo->prepare("SELECT * FROM ingatlanok WHERE id = :id");
  $st->execute([':id' => $id]);
  if ($row = $st->fetch()) $data = array_merge($data, $row);

  $st2 = $pdo->prepare("SELECT id, kep_url, is_cover FROM ingatlan_kepek WHERE ingatlan_id = :id ORDER BY id ASC");
  $st2->execute([':id' => $id]);
  $kepek = $st2->fetchAll();
}

// Jelenlegi címkék (SET) -> tömb
$currentTags = array_filter(array_map('trim', explode(',', (string)($data['jeloles'] ?? ''))));

// Útvonalak
$adminBase = rtrim(dirname($_SERVER['PHP_SELF']), '/'); // pl. /nido/admin
$rootBase  = rtrim(dirname($adminBase), '/');           // pl. /nido

$action = $id > 0 ? "frissites.php?id={$id}" : "mentes.php";
?>
<form action="<?= htmlspecialchars($action) ?>" method="post" enctype="multipart/form-data" class="space-y-6">
  <?php if ($id > 0): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $id ? 'Ingatlan szerkesztése' : 'Új ingatlan' ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
<header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
  <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
    <a href="<?= $adminBase ?>/ingatlanok.php" class="font-bold text-pink-800">NIDO – Admin</a>
    <nav class="text-sm flex gap-4">
      <a class="hover:text-pink-800" href="<?= $rootBase ?>/index.php" target="_blank">Nyilvános oldal</a>
      <a class="hover:text-red-700 font-semibold" href="<?= $adminBase ?>/logout.php">Kilépés</a>
    </nav>
  </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-8">
  <h1 class="text-2xl font-bold mb-6"><?= $id ? 'Ingatlan szerkesztése' : 'Új ingatlan felvétele' ?></h1>

  <form action="<?= htmlspecialchars($action) ?>" method="post" enctype="multipart/form-data" class="space-y-6">

    <!-- Alapadatok -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm mb-1">Típus</label>
        <select name="tipus" class="w-full border rounded px-3 py-2" required>
          <?php foreach ($OPT_TIPUS as $v): ?>
            <option value="<?= htmlspecialchars($v) ?>" <?= ($data['tipus']??'')===$v?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm mb-1">Város</label>
        <input name="varos" value="<?= htmlspecialchars($data['varos']) ?>" class="w-full border rounded px-3 py-2" required>
      </div>

      <div>
        <label class="block text-sm mb-1">Utca</label>
        <input name="utca" value="<?= htmlspecialchars($data['utca']) ?>" class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm mb-1">Ár (Ft)</label>
        <input name="ar_ft" value="<?= htmlspecialchars($data['ar_ft']) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm mb-1">Alapterület (m²)</label>
        <input name="alapterulet" value="<?= htmlspecialchars($data['alapterulet']) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm mb-1">Szobaszám</label>
        <input name="szobaszam" value="<?= htmlspecialchars($data['szobaszam']) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm mb-1">Félszoba</label>
        <input name="felszoba" value="<?= htmlspecialchars($data['felszoba']) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm mb-1">Emelet</label>
        <input name="emelet" value="<?= htmlspecialchars($data['emelet']) ?>" class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm mb-1">Falazat</label>
        <select name="falazat" class="w-full border rounded px-3 py-2">
          <?php foreach ($OPT_FALAZAT as $v): ?>
            <option value="<?= htmlspecialchars($v) ?>" <?= ($data['falazat']??'')===$v?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm mb-1">Állapot</label>
        <select name="allapot" class="w-full border rounded px-3 py-2">
          <?php foreach ($OPT_ALLAPOT as $v): ?>
            <option value="<?= htmlspecialchars($v) ?>" <?= ($data['allapot']??'')===$v?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm mb-1">Státusz</label>
        <select name="statusz" class="w-full border rounded px-3 py-2">
          <?php foreach ($OPT_STATUSZ as $v): ?>
            <option value="<?= htmlspecialchars($v) ?>" <?= ($data['statusz']??'')===$v?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Leírás -->
    <div>
      <label class="block text-sm mb-1">Leírás</label>
      <textarea name="leiras" rows="4" class="w-full border rounded px-3 py-2"><?= htmlspecialchars($data['leiras']) ?></textarea>
    </div>

    <!-- Címkék (SET) -->
    <div>
      <div class="block text-sm mb-1">Címkék (több választható)</div>
      <div class="flex flex-wrap gap-4">
        <?php foreach ($OPT_JELOLES as $tag): ?>
          <?php $checked = in_array($tag, $currentTags, true) ? 'checked' : ''; ?>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="jeloles[]" value="<?= htmlspecialchars($tag) ?>" <?= $checked ?>>
            <span><?= htmlspecialchars($tag) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Meglévő képek (csak szerkesztésnél) -->
    <?php if ($id > 0): ?>
      <div>
        <div class="font-medium mb-2">Meglévő képek</div>
        <?php if ($kepek): ?>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <?php foreach ($kepek as $k): ?>
              <label class="block border rounded overflow-hidden bg-white shadow-sm">
              <?php
                // kezeli a /uploads/... és az uploads/... formátumot is
                $imgUrl = $rootBase . '/' . ltrim((string)$k['kep_url'], '/');
               ?>
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" style="width:100%;height:140px;object-fit:cover">

                <div class="p-2 flex items-center justify-between text-sm">
                  <label class="inline-flex items-center gap-2">
                    <input type="radio" name="existing_cover_id" value="<?= (int)$k['id'] ?>" <?= $k['is_cover'] ? 'checked' : '' ?>>
                    <span><?= $k['is_cover'] ? 'Borító' : 'Beállít borítónak' ?></span>
                  </label>
                  <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="torol_kepek[]" value="<?= (int)$k['id'] ?>"> <span>Törlés</span>
                  </label>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="text-xs text-gray-500 mt-2">„Törlés” csak mentés után lép érvénybe. Ha mindent törölsz, a rendszer az első újonnan feltöltött képet teszi borítónak.</p>
        <?php else: ?>
          <p class="text-sm text-gray-500">Ehhez az ingatlanhoz még nincs kép.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Új képek feltöltése (kötegelt) + borító választás az új kötegből -->
    <div>
      <label class="block text-sm mb-2">Új képek feltöltése (több is választható)</label>
      <input type="file" name="kepek[]" id="kepek" multiple accept="image/*" class="block">
      <input type="hidden" name="cover_index" id="cover_index" value="0">
      <div id="preview" class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3"></div>
      <p class="text-xs text-gray-500 mt-1">
        Válaszd ki, melyik legyen a <strong>borítókép</strong> az újonnan feltöltöttek közül. Ha nem jelölsz meg egyet sem,
        az első új kép lesz borító (vagy ha nem töltesz fel újat, marad a jelenlegi borító).
      </p>
    </div>

    <div class="flex items-center gap-3">
      <button class="bg-pink-800 text-white px-4 py-2 rounded hover:bg-pink-900"><?= $id ? 'Mentés' : 'Létrehozás' ?></button>
      <a class="text-gray-700 hover:underline" href="<?= $adminBase ?>/ingatlanok.php">Vissza a listához</a>
    </div>
  </form>
</main>

<script>
  // Új képek előnézet + borítókép választás
  const input = document.getElementById('kepek');
  const preview = document.getElementById('preview');
  const coverIndex = document.getElementById('cover_index');

  if (input) {
    input.addEventListener('change', () => {
      preview.innerHTML = '';
      const files = [...input.files];
      if (files.length === 0) { coverIndex.value = ''; return; }

      files.forEach((file, idx) => {
        const url = URL.createObjectURL(file);
        const wrap = document.createElement('label');
        wrap.className = 'border rounded overflow-hidden block cursor-pointer bg-white shadow';
        wrap.innerHTML = `
          <img src="${url}" alt="" style="width:100%;height:140px;object-fit:cover">
          <div class="p-2 flex items-center gap-2 text-sm">
            <input type="radio" name="cover_choice" value="${idx}" ${idx===0?'checked':''}>
            <span>Borítókép (új)</span>
          </div>
        `;
        preview.appendChild(wrap);
      });

      // alapból az első új fájl a borító
      coverIndex.value = '0';

      // egyszer állítjuk be az eseményfigyelőt ehhez a batch-hez
      preview.addEventListener('change', (e) => {
        if (e.target && e.target.name === 'cover_choice') {
          coverIndex.value = String(e.target.value);
        }
      }, { once: true });
    });
  }
</script>
</body>
</html>
