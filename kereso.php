<?php
/* kereso.php — Nido Ingatlan: összetett kereső + találati lista */

ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

/* ====== DB ====== */
$DB_HOST='127.0.0.1'; $DB_PORT=3306; $DB_NAME='igngatlan_db'; $DB_USER='nidoapp'; $DB_PASS='ValamiErősJelszó123!';
try{
  $pdo=new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",$DB_USER,$DB_PASS,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
}catch(Throwable $e){ http_response_code(500); exit("Adatbázis hiba: ".htmlspecialchars($e->getMessage())); }

/* ====== Segédek ====== */
function e($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function hu_price($n){ return $n!==null?number_format((float)$n,0,',',' ').' Ft':'—'; }
function base_url_prefix(){ return rtrim(dirname($_SERVER['PHP_SELF']),'/'); }
function asset_url($path){ $path=ltrim((string)$path,'/'); return base_url_prefix().'/'.$path; }

/* ====== Opciók (ENUM/SET) ====== */
$OPT_TIPUS   = ['Lakás','Ház','Telek','Garázs','Üzlethelyiség','Tároló'];
$OPT_STATUSZ = ['Aktív','Foglalva','Eladva'];           // jellemzően Aktívra keresünk, de választható
$OPT_JELOLES = ['Új a kínálatban','Kiemelt','Árcsökkenés'];

/* ====== Query paramok (GET) ====== */
$q        = trim($_GET['q'] ?? '');                 // város/utca szabadszavas
$tipus    = $_GET['tipus']   ?? '';                 // ENUM
$statusz  = $_GET['statusz'] ?? 'Aktív';            // ENUM (alap: Aktív)
$ar_min   = $_GET['ar_min']  ?? '';
$ar_max   = $_GET['ar_max']  ?? '';
$m2_min   = $_GET['m2_min']  ?? '';
$m2_max   = $_GET['m2_max']  ?? '';
$tags     = $_GET['jeloles'] ?? [];                 // SET tömb (checkbox)
$order    = $_GET['rendez']  ?? 'ujak';             // 'ujak' | 'ar_no' | 'ar_csokk'
$page     = max(1,(int)($_GET['page'] ?? 1));
$limit    = 12;
$offset   = ($page-1)*$limit;

/* Normalizálás */
if(!in_array($tipus,$OPT_TIPUS,true))   $tipus='';
if(!in_array($statusz,$OPT_STATUSZ,true)) $statusz='Aktív';
$tags = array_values(array_intersect((array)$tags,$OPT_JELOLES));
$ar_min = $ar_min!=='' ? max(0,(int)preg_replace('/\D+/','',$ar_min)) : '';
$ar_max = $ar_max!=='' ? max(0,(int)preg_replace('/\D+/','',$ar_max)) : '';
$m2_min = $m2_min!=='' ? max(0,(int)$m2_min) : '';
$m2_max = $m2_max!=='' ? max(0,(int)$m2_max) : '';

/* ====== WHERE építés ====== */
$where = ["1=1"];
$params = [];

if($q!==''){
  $where[] = "(i.varos LIKE :q OR i.utca LIKE :q)";
  $params[':q']="%$q%";
}
if($tipus!==''){
  $where[]="i.tipus=:tipus"; $params[':tipus']=$tipus;
}
if($statusz!==''){
  $where[]="i.statusz=:statusz"; $params[':statusz']=$statusz;
}
if($ar_min!==''){ $where[]="i.ar_ft>=:ar_min"; $params[':ar_min']=$ar_min; }
if($ar_max!==''){ $where[]="i.ar_ft<=:ar_max"; $params[':ar_max']=$ar_max; }
if($m2_min!==''){ $where[]="i.alapterulet>=:m2_min"; $params[':m2_min']=$m2_min; }
if($m2_max!==''){ $where[]="i.alapterulet<=:m2_max"; $params[':m2_max']=$m2_max; }
/* SET jelölések — minden kipipált címkének szerepelnie kell */
foreach($tags as $idx=>$t){
  $key=":tag$idx";
  $where[]="FIND_IN_SET($key, i.jeloles) > 0";
  $params[$key]=$t;
}
$where_sql = implode(' AND ',$where);

/* ====== Rendezes ====== */
switch($order){
  case 'ar_no':     $order_sql = "i.ar_ft ASC NULLS LAST"; break;
  case 'ar_csokk':  $order_sql = "i.ar_ft DESC NULLS LAST"; break;
  default:          $order_sql = "i.letrehozva DESC";
}

/* ====== Borítókép join ====== */
$coverJoin = "
LEFT JOIN (
  SELECT ik.ingatlan_id, ik.kep_url
  FROM ingatlan_kepek ik
  JOIN (
    SELECT ingatlan_id, MAX(is_cover) AS has_cover, MIN(id) AS first_id,
           COALESCE(MAX(CASE WHEN is_cover=1 THEN id END), MIN(id)) AS chosen_id
    FROM ingatlan_kepek
    GROUP BY ingatlan_id
  ) x ON x.chosen_id = ik.id
) k ON k.ingatlan_id = i.id
";

/* ====== Count + Találatok ====== */
$countSql = "SELECT COUNT(*) FROM ingatlanok i $coverJoin WHERE $where_sql";
$st=$pdo->prepare($countSql); $st->execute($params); $total=(int)$st->fetchColumn();

$listSql = "
SELECT i.id,i.varos,i.utca,i.ar_ft,i.jeloles,i.letrehozva,i.statusz,k.kep_url AS boritokep
FROM ingatlanok i
$coverJoin
WHERE $where_sql
ORDER BY $order_sql
LIMIT :lim OFFSET :off
";
$st=$pdo->prepare($listSql);
foreach($params as $k=>$v){ $st->bindValue($k,$v); }
$st->bindValue(':lim',$limit,PDO::PARAM_INT);
$st->bindValue(':off',$offset,PDO::PARAM_INT);
$st->execute();
$rows=$st->fetchAll();

/* ====== Kártya ====== */
function card_html($i){
  $raw   = $i['boritokep'] ?: 'uploads/placeholder.jpg';
  $img   = e(asset_url($raw));
  $title = e(trim(($i['varos']??'').' — '.($i['utca']??'')) ?: 'Ingatlan');
  $price = hu_price($i['ar_ft']??null);
  $link  = 'ingatlan.php?id='.(int)$i['id'];

  // badge a SET alapján
  $badge=''; $tags=array_filter(array_map('trim',explode(',',(string)($i['jeloles']??''))));
  if(in_array('Kiemelt',$tags,true)){
    $badge='<span class="absolute top-2 right-2 bg-purple-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Kiemelt</span>';
  }elseif(in_array('Árcsökkenés',$tags,true)){
    $badge='<span class="absolute top-2 right-2 bg-red-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Árcsökkent</span>';
  }elseif(in_array('Új a kínálatban',$tags,true)){
    $badge='<span class="absolute top-2 right-2 bg-green-700 text-white text-xs font-semibold px-3 py-1 rounded-full shadow">Új</span>';
  }

  ob_start(); ?>
  <article class="relative bg-white rounded-2xl shadow hover:shadow-lg transition overflow-hidden">
    <?= $badge ?>
    <img src="<?= $img ?>" alt="<?= $title ?>" class="w-full h-44 object-cover">
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
  <title>Kereső – Nido Ingatlan</title>
  <meta name="description" content="Ingatlan kereső: város, típus, ár, alapterület, címkék szerint.">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
<header class="bg-gradient-to-r from-gray-100 to-gray-200 border-b border-gray-200">
  <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
    <a href="<?= e(base_url_prefix()) ?>/index.php" class="font-bold text-pink-900">nidoingatlan.hu</a>
    <nav class="text-sm flex gap-6">
      <a class="hover:text-pink-900" href="<?= e(base_url_prefix()) ?>/index.php">Főoldal</a>
      <!-- <a class="hover:text-pink-900" href="<?= e(base_url_prefix()) ?>/rolam.html">Rólam</a> -->
      <a class="hover:text-pink-900" href="<?= e(base_url_prefix()) ?>/kereso.php">Kereső</a>
      <a class="hover:text-pink-900" href="https://startolj-ra.hu/" target="_blank" rel="noopener noreferrer">Otthon Start</a>
      <a class="hover:text-pink-900" href="https://www.mnb.hu/fogyasztovedelem/hitel-lizing/jelzalog-hitelek/csok-plusz-hitelprogram" target="_blank" rel="noopener noreferrer">CSOK +</a>
    </nav>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6 w-full">
  <h1 class="text-2xl font-bold mb-4">Kereső</h1>

  <!-- Szűrők -->
  <form method="get" class="bg-white border rounded-xl p-4 grid gap-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm mb-1">Város / utca</label>
        <input name="q" value="<?= e($q) ?>" class="w-full border rounded px-3 py-2" placeholder="pl. Győr vagy Andrássy">
      </div>
      <div>
        <label class="block text-sm mb-1">Típus</label>
        <select name="tipus" class="w-full border rounded px-3 py-2">
          <option value="">— bármely —</option>
          <?php foreach($OPT_TIPUS as $v): ?>
            <option value="<?= e($v) ?>" <?= $tipus===$v?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Státusz</label>
        <select name="statusz" class="w-full border rounded px-3 py-2">
          <?php foreach($OPT_STATUSZ as $v): ?>
            <option value="<?= e($v) ?>" <?= $statusz===$v?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Rendezés</label>
        <select name="rendez" class="w-full border rounded px-3 py-2">
          <option value="ujak"     <?= $order==='ujak'?'selected':'' ?>>Legújabb elöl</option>
          <option value="ar_no"    <?= $order==='ar_no'?'selected':'' ?>>Ár szerint növekvő</option>
          <option value="ar_csokk" <?= $order==='ar_csokk'?'selected':'' ?>>Ár szerint csökkenő</option>
        </select>
      </div>

      <div>
        <label class="block text-sm mb-1">Ár (min)</label>
        <input name="ar_min" value="<?= e($ar_min) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2" placeholder="pl. 20000000">
      </div>
      <div>
        <label class="block text-sm mb-1">Ár (max)</label>
        <input name="ar_max" value="<?= e($ar_max) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2" placeholder="pl. 120000000">
      </div>
      <div>
        <label class="block text-sm mb-1">Alapterület (min m²)</label>
        <input name="m2_min" value="<?= e($m2_min) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Alapterület (max m²)</label>
        <input name="m2_max" value="<?= e($m2_max) ?>" inputmode="numeric" class="w-full border rounded px-3 py-2">
      </div>
    </div>

    <div>
      <div class="block text-sm mb-1">Címkék</div>
      <div class="flex flex-wrap gap-4">
        <?php foreach($OPT_JELOLES as $tag): ?>
          <?php $checked = in_array($tag,$tags,true) ? 'checked' : ''; ?>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="jeloles[]" value="<?= e($tag) ?>" <?= $checked ?>>
            <span><?= e($tag) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button class="bg-pink-900 text-white px-4 py-2 rounded hover:bg-pink-950">Keresés</button>
      <a class="text-sm hover:underline" href="kereso.php">Szűrők törlése</a>
      <span class="text-sm text-gray-500 ml-auto">Találatok: <?= (int)$total ?></span>
    </div>
  </form>

  <!-- Találatok -->
  <section class="mt-6">
    <?php if($rows): ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($rows as $r) echo card_html($r); ?>
      </div>

      <!-- Lapozó -->
      <?php
        $pages = max(1, (int)ceil($total/$limit));
        if($pages>1):
          $qs=$_GET; unset($qs['page']);
          $base='?'.http_build_query($qs);
          if($base==='?') $base='?';
          else $base.='&';
      ?>
      <div class="mt-6 flex items-center justify-center gap-2">
        <?php if($page>1): ?><a class="px-3 py-2 border rounded hover:bg-gray-50" href="<?= e($base.'page='.($page-1)) ?>">&laquo; Előző</a><?php endif; ?>
        <span class="px-3 py-2 text-sm text-gray-600">Oldal <?= (int)$page ?> / <?= (int)$pages ?></span>
        <?php if($page<$pages): ?><a class="px-3 py-2 border rounded hover:bg-gray-50" href="<?= e($base.'page='.($page+1)) ?>">Következő &raquo;</a><?php endif; ?>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <p class="text-center text-gray-500">Nincs találat a megadott szűrőkkel.</p>
    <?php endif; ?>
  </section>
</main>

<footer class="mt-auto bg-gray-900 text-gray-200">
  <div class="max-w-7xl mx-auto px-4 py-8 grid gap-3 text-sm sm:flex sm:items-center sm:justify-between">
    <div class="flex items-center gap-3">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-2.05a1.5 1.5 0 0 0-1.264-1.482l-3.338-.557a1.5 1.5 0 0 0-1.286.43l-.97.97a12.035 12.035 0 0 1-5.385-5.385l.97-.97a1.5 1.5 0 0 0 .43-1.286l-.557-3.338A1.5 1.5 0 0 0 8.55 3.75H6.5A2.25 2.25 0 0 0 4.25 6v.75z"/></svg>
      <a href="tel:+36204465216" class="text-base hover:text-white">+36 20 446 5216</a>
    </div>
    <div class="flex items-center gap-3">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.486 2 2 6.262 2 11.5S6.486 21 12 21c2.43 0 4.657-.816 6.33-2.19a1 1 0 1 0-1.286-1.536A8.022 8.022 0 0 1 12 19C7.589 19 4 15.865 4 11.5S7.589 4 12 4s8 3.135 8 7.5v.75c0 1.447-1.18 2.625-2.636 2.625-.868 0-1.636-.41-2.105-1.037A4.497 4.497 0 0 1 7.5 11.5a4.5 4.5 0 0 1 8.824-1.5 1 1 0 1 0 1.852-.74A6.5 6.5 0 1 0 18 15.75v.25c0 .241.195.437.436.437C19.228 16.437 20 15.664 20 14.75V11.5C20 6.262 17.514 2 12 2Zm0 6a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/></svg>
      <a href="mailto:erika@nidoingatlan.hu" class="text-base hover:text-white">erika@nidoingatlan.hu</a>
    </div>
    <div class="text-gray-400">&copy; <?= date('Y') ?> Nido Ingatlan</div>
  </div>
</footer>
</body>
</html>
