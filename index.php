<?php
// Adatbázis kapcsolat
$host = "localhost";
$user = "root";   // MAMP alap user
$pass = "root";   // MAMP alap jelszó
$dbname = "ingatlan_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

// Lekérdezés – minden ingatlanhoz egy kép (a legelső)
$sql = "
SELECT i.id, i.tipus, i.varos, i.utca, i.emelet, i.alapterulet, 
       i.szobaszam, i.felszoba, i.ar, i.statusz, i.jeloles, 
       SUBSTRING_INDEX(GROUP_CONCAT(k.kep_url ORDER BY k.id ASC), ',', 1) AS kep
FROM ingatlanok i
LEFT JOIN ingatlan_kepek k ON i.id = k.ingatlan_id
GROUP BY i.id
ORDER BY i.letrehozva DESC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ingatlanok listája</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa; /* világos szürke */
    }
    .card {
      border: 1px solid #ddd;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      transition: transform 0.2s;
    }
    .card:hover {
      transform: translateY(-5px);
    }
    .badge-pink {
      background-color: #e83e8c; /* pink */
    }
  </style>
</head>
<body>
  <div class="container my-5">
    <h1 class="mb-4 text-center text-secondary">Ingatlan kínálat</h1>
    <div class="row g-4">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <div class="col-md-4">
            <div class="card h-100">
              <?php if ($row['kep']): ?>
                <img src="<?= htmlspecialchars($row['kep']) ?>" class="card-img-top" alt="Ingatlan kép">
              <?php else: ?>
                <img src="placeholder.jpg" class="card-img-top" alt="Nincs kép">
              <?php endif; ?>
              <div class="card-body">
                <h5 class="card-title text-dark">
                  <?= htmlspecialchars($row['tipus']) ?> - <?= htmlspecialchars($row['varos']) ?>
                </h5>
                <p class="card-text text-muted">
                  <?= htmlspecialchars($row['utca']) ?> <?= $row['emelet'] ? ", " . htmlspecialchars($row['emelet']) : "" ?><br>
                  Alapterület: <?= (int)$row['alapterulet'] ?> m²<br>
                  Szobák: <?= (int)$row['szobaszam'] ?><?php if ($row['felszoba'] > 0) echo " + " . (int)$row['felszoba'] . " félszoba"; ?>
                </p>
                <span class="badge <?= $row['jeloles'] === 'Új a kínálatban' ? 'badge-pink' : 'bg-secondary' ?>">
                  <?= htmlspecialchars($row['jeloles']) ?>
                </span>
                <span class="badge bg-light text-dark">
                  <?= htmlspecialchars($row['statusz']) ?>
                </span>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center text-muted">Jelenleg nincs elérhető ingatlan.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>
