<?php
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/../config.php';


$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];

if ($q !== '') {
  $where = "WHERE (varos LIKE :q OR utca LIKE :q OR tipus LIKE :q OR statusz LIKE :q OR jeloles LIKE :q)";
  $params[':q'] = "%$q%";
}

$sql = "
SELECT id, tipus, varos, utca, alapterulet, szobaszam, felszoba, ar_ft, jeloles, statusz, letrehozva, modositva
FROM ingatlanok
$where
ORDER BY
  (statusz = 'Aktív') DESC,                    -- aktívak elöl
  (jeloles = 'Új a kínálatban') DESC,          -- majd jelöltek
  (jeloles = 'Árcsökkenés') DESC,
  letrehozva DESC
LIMIT 500
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
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
    <a href="/admin/ingatlanok.php" class="font-bold text-pink-800">NIDO – Admin</a>
    <nav class="text-sm flex gap-4">
      <a class="hover:text-pink-800" href="/index.html" target="_blank">Nyilvános oldal</a>
      <a class="hover:text-pink-800" href="/admin/szerkesztes.php">+ Új ingatlan</a>
      <a class="hover:text-red-700 font-semibold" href="/admin/logout.php">Kilépés</a>
    </nav>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <form method="get" class="flex gap-2 items-center">
      <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Keresés (város, utca, típus, státusz, jelölés)"
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
          <th class="p-3 text-center">Jelölés</th>
          <th class="p-3 text-center">Státusz</th>
          <th class="p-3 text-right">Műveletek</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="p-3"><?= (int)$r['id'] ?></td>
          <td class="p-3">
            <div class="font-medium"><?= htmlspecialchars($r['varos']) ?></div>
            <div class="text-gray-500"><?= htmlspecialchars($r['utca']) ?></div>
            <div class="text-[11px] text-gray-400">
              Létrehozva: <?= htmlspecialchars($r['letrehozva']) ?>
              <?php if(!empty($r['modositva'])): ?> · Mód: <?= htmlspecialchars($r['modositva']) ?><?php endif; ?>
            </div>
          </td>
          <td class="p-3 text-center"><?= htmlspecialchars($r['tipus']) ?></td>
          <td class="p-3 text-center">
            <?= (int)$r['alapterulet'] ?> m² • <?= (float)$r['szobaszam'] ?> szoba<?= $r['felszoba'] ? ' + '.(int)$r['felszoba'].' fél' : '' ?>
          </td>
          <td class="p-3 text-center"><?= number_format((float)($r['ar_ft']??0),0,',',' ') ?> Ft</td>
          <td class="p-3 text-center"><?= htmlspecialchars($r['jeloles'] ?: 'Nincs') ?></td>
          <td class="p-3 text-center">
            <?php if ($r['statusz'] && $r['statusz'] !== 'Aktív'): ?>
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
        <tr><td class="p-6 text-center text-gray-500" colspan="8">Nincs találat.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>

