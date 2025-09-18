<?php
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/../config.php';

$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];

if ($q !== '') {
  $where = "WHERE (i.varos LIKE :q OR i.utca LIKE :q OR i.tipus LIKE :q OR i.statusz LIKE :q OR i.jeloles LIKE :q)";
  $params[':q'] = "%$q%";
}

/* Képek összesítése: hány kép + van-e borító */
$sql = "
SELECT 
  i.id, i.tipus, i.varos, i.utca, i.alapterulet, i.szobaszam, i.felszoba, 
  i.ar_ft, i.jeloles, i.statusz, i.letrehozva,
  COALESCE(k.kep_db, 0) AS kep_db,
  COALESCE(k.van_borito, 0) AS van_borito
FROM ingatlanok i
LEFT JOIN (
  SELECT ingatlan_id,
         COUNT(*) AS kep_db,
         MAX(CASE WHEN is_cover = 1 THEN 1 ELSE 0 END) AS van_borito
  FROM ingatlan_kepek
  GROUP BY ingatlan_id
) k ON k.ingatlan_id = i.id
$where
ORDER BY
  (i.statusz = 'Aktív') DESC,       -- aktívak elöl
  (i.jeloles = 'Új') DESC,          -- aztán az Új
  (i.jeloles = 'Árcsökkent') DESC,  -- majd az Árcsökkent
  i.letrehozva DESC
LIMIT 500
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

/* Útvonalak (relatívak, almappában is működnek) */
$adminBase = rtrim(dirname($_SERVER['PHP_SELF']), '/');        // pl. /nido/admin
$rootBase  = rtrim(dirname($adminBase), '/');                  // pl. /nido
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin – Ingatlanok</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
<header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
  <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
    <a href="<?= $adminBase ?>/ingatlanok.php" class="font-bold text-pink-800">NIDO – Admin</a>
    <nav class="text-sm flex gap-4">
      <a class="hover:text-pink-800" href="<?= $rootBase ?>/index.php" target="_blank">Nyilvános oldal</a>
      <a class="hover:text-pink-800" href="<?= $adminBase ?>/szerkesztes.php">+ Új ingatlan</a>
      <a class="hover:text-red-700 font-semibold" href="<?= $adminBase ?>/logout.php">Kilépés</a>
    </nav>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <form method="get" class="flex gap-2 items-center">
      <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Keresés (város, utca, típus, státusz, jelölés)"
             class="border rounded-lg px-3 py-2 w-72">
      <button class="bg-gray-900 text-white px-4 py-2 rounded-lg">Keres</button>
      <?php if ($q!==''): ?><a class="px-3 py-2" href="ingatlanok.php">Törlés</a><?php endif; ?>
    </form>
    <a href="szerkesztes.php" class="bg-pink-800 text-white px-4 py-2 rounded-lg hover:bg-pink-900">+ Új ingatlan</a>
  </div>

  <div class="bg-white border rounded-xl overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-3 text-left">#</th>
          <th class="p-3 text-left">Cím</th>
          <th class="p-3 text-center">Típus</th>
          <th class="p-3 text-center">m² / szobák</th>
          <th class="p-3 text-center">Ár</th>
          <th class="p-3 text-center">Címke</th>
          <th class="p-3 text-center">Képek</th>
          <th class="p-3 text-center">Státusz</th>
          <th class="p-3 text-right">Műveletek</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="p-3"><?= (int)$r['id'] ?></td>
          <td class="p-3">
            <div class="font-medium"><?= htmlspecialchars($r['varos'] ?? '') ?></div>
            <div class="text-gray-500"><?= htmlspecialchars($r['utca'] ?? '') ?></div>
            <div class="text-[11px] text-gray-400">
              Létrehozva: <?= htmlspecialchars($r['letrehozva'] ?? '') ?>
            </div>
          </td>
          <td class="p-3 text-center"><?= htmlspecialchars($r['tipus'] ?? '') ?></td>
          <td class="p-3 text-center">
            <?= (int)($r['alapterulet'] ?? 0) ?> m² • <?= (float)($r['szobaszam'] ?? 0) ?> szoba<?= !empty($r['felszoba']) ? ' + '.(int)$r['felszoba'].' fél' : '' ?>
          </td>
          <td class="p-3 text-center"><?= number_format((float)($r['ar_ft'] ?? 0), 0, ',', ' ') ?> Ft</td>
          <td class="p-3 text-center"><?= htmlspecialchars($r['jeloles'] ?: '—') ?></td>
          <td class="p-3 text-center">
            <?php if ((int)$r['kep_db'] === 0): ?>
              <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">nincs kép</span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">
                <?= (int)$r['kep_db'] ?> kép<?= (int)$r['van_borito'] === 1 ? ' • borító OK' : ' • nincs borító' ?>
              </span>
            <?php endif; ?>
          </td>
          <td class="p-3 text-center">
            <?php if (!empty($r['statusz']) && $r['statusz'] !== 'Aktív'): ?>
              <span class="px-2 py-1 rounded-full text-xs bg-gray-800 text-white"><?= htmlspecialchars($r['statusz']) ?></span>
            <?php else: ?>
              <span class="text-gray-400">Aktív</span>
            <?php endif; ?>
          </td>
          <td class="p-3 text-right whitespace-nowrap">
            <a class="px-3 py-1 border rounded hover:bg-gray-50" href="szerkesztes.php?id=<?= (int)$r['id'] ?>">Szerk.</a>
            <a class="ml-2 px-3 py-1 border rounded text-red-700 hover:bg-red-50"
               href="torles.php?id=<?= (int)$r['id'] ?>"
               onclick="return confirm('Biztos törlöd az #<?= (int)$r['id'] ?> rekordot? A képei is törlődnek.');">
               Törlés
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td class="p-6 text-center text-gray-500" colspan="9">Nincs találat.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
