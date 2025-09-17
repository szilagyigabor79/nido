<?php
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);

/* Alapértékek */
$adat = [
  'tipus'        => 'Lakás',
  'varos'        => '',
  'utca'         => '',
  'emelet'       => '',
  'alapterulet'  => '',
  'szobaszam'    => '',
  'felszoba'     => 0,
  'ar_ft'        => '',
  'leiras'       => '',
  'falazat'      => 'Tégla',
  'allapot'      => 'Átlagos',
  'statusz'      => 'Aktív',               // ENUM: Aktív | Foglalva | Eladva
  'jeloles'      => 'Nincs',               // ENUM: Nincs | Új a kínálatban | Árcsökkenés
  'boritokep'    => ''
];
$kepek = [];

/* Szerkesztés betöltés */
if ($id) {
  $st = $pdo->prepare("SELECT * FROM ingatlanok WHERE id=? LIMIT 1");
  $st->execute([$id]);
  if ($row = $st->fetch()) $adat = array_merge($adat, $row);

  $st2 = $pdo->prepare("SELECT id, kep_url FROM ingatlan_kepek WHERE ingatlan_id=? ORDER BY id");
  $st2->execute([$id]);
  $kepek = $st2->fetchAll();
}

/* ENUM készletek – igazítsd a sémádhoz, ha eltér */
$enumTipus   = ['Lakás','Ház','Telek','Garázs','Üzlethelyiség','Tároló'];
$enumFalazat = ['Tégla','Beton','Ytong','Panel','Csúsztatott zsalus','Vegyes','Vályog'];
$enumAllapot = ['Új','Újszerű','Felújított','Átlagos','Közepes','Felújítandó'];
$enumStatusz = ['Aktív','Foglalva','Eladva'];
$enumJeloles = ['Nincs','Új a kínálatban','Árcsökkenés'];
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $id ? 'Ingatlan szerkesztése' : 'Új ingatlan' ?> – Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
  <header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
      <a href="/admin/ingatlanok.php" class="font-bold text-pink-800">NIDO – Admin</a>
      <nav class="text-sm flex gap-4">
        <a class="hover:text-pink-800" href="/admin/ingatlanok.php">Vissza a listához</a>
        <a class="hover:text-pink-800" href="/index.html" target="_blank">Nyilvános oldal</a>
        <a class="hover:text-red-700 font-semibold" href="/admin/logout.php">Kilépés</a>
      </nav>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-4"><?= $id ? 'Ingatlan szerkesztése' : 'Új ingatlan' ?></h1>

    <form action="mentes.php" method="post" enctype="multipart/form-data" class="bg-white border rounded-xl p-6 grid gap-6">
      <input type="hidden" name="id" value="<?= $id ?>">

      <div class="grid sm:grid-cols-2 gap-4">
        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Típus</span>
          <select name="tipus" class="border rounded-lg px-3 py-2">
            <?php foreach($enumTipus as $v): ?>
              <option <?= $adat['tipus']===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Város</span>
          <input name="varos" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['varos']) ?>" required>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Utca, házszám</span>
          <input name="utca" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['utca']) ?>" required>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Emelet</span>
          <input name="emelet" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['emelet']) ?>">
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Alapterület (m²)</span>
          <input type="number" name="alapterulet" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['alapterulet']) ?>" required>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Szobaszám</span>
          <input type="number" step="0.5" name="szobaszam" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['szobaszam']) ?>" required>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Félszoba</span>
          <input type="number" name="felszoba" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['felszoba']) ?>">
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Ár (Ft)</span>
          <input type="number" name="ar_ft" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['ar_ft']) ?>">
        </label>
      </div>

      <label class="grid gap-1">
        <span class="text-sm text-gray-600">Leírás</span>
        <textarea name="leiras" rows="5" class="border rounded-lg px-3 py-2"><?= htmlspecialchars($adat['leiras']) ?></textarea>
      </label>

      <div class="grid sm:grid-cols-3 gap-4">
        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Falazat</span>
          <select name="falazat" class="border rounded-lg px-3 py-2">
            <?php foreach($enumFalazat as $v): ?>
              <option <?= $adat['falazat']===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Állapot</span>
          <select name="allapot" class="border rounded-lg px-3 py-2">
            <?php foreach($enumAllapot as $v): ?>
              <option <?= $adat['allapot']===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Státusz</span>
          <select name="statusz" class="border rounded-lg px-3 py-2">
            <?php foreach($enumStatusz as $v): ?>
              <option <?= $adat['statusz']===$v?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label class="grid gap-1">
        <span class="text-sm text-gray-600">Jelölés (egycímkés)</span>
        <select name="jeloles" class="border rounded-lg px-3 py-2">
          <?php foreach($enumJeloles as $v): ?>
            <option <?= $adat['jeloles']===$v?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="grid sm:grid-cols-2 gap-4">
        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Borítókép (relatív útvonal vagy fájlfeltöltés)</span>
          <input name="boritokep" class="border rounded-lg px-3 py-2" value="<?= htmlspecialchars($adat['boritokep']) ?>" placeholder="pl. uploads/xxx.jpg">
          <input type="file" name="boritokep_file" accept="image/*" class="mt-2">
          <?php if(!empty($adat['boritokep'])): ?>
            <img src="/<?= htmlspecialchars($adat['boritokep']) ?>" class="mt-2 h-24 object-cover rounded" alt="Borítókép">
          <?php endif; ?>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-gray-600">További képek (több fájl választható)</span>
          <input type="file" name="kepek[]" accept="image/*" multiple>
          <?php if($kepek): ?>
            <div class="grid grid-cols-4 gap-2 mt-2">
              <?php foreach($kepek as $k): ?>
                <div class="relative">
                  <img src="/<?= htmlspecialchars($k['kep_url']) ?>" class="h-20 w-full object-cover rounded">
                  <a class="absolute -top-2 -right-2 bg-white rounded-full border px-2 py-0.5 text-xs"
                     href="torol_kep.php?id=<?= (int)$k['id'] ?>&iid=<?= $id ?>" onclick="return confirm('Törlöd a képet?')">×</a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </label>
      </div>

      <div class="flex justify-end gap-3">
        <a href="ingatlanok.php" class="px-4 py-2 border rounded-lg">Mégse</a>
        <button class="bg-pink-800 text-white px-4 py-2 rounded-lg hover:bg-pink-900">Mentés</button>
      </div>
    </form>
  </main>
</body>
</html>
