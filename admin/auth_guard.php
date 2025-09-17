<?php
session_start();

if (!isset($_SESSION['uid'])) {
  header('Location: /nido/admin/login.html');
  exit;
}

// ha csak aktív adminok férhetnek hozzá
if (($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  exit('Nincs jogosultságod ehhez az oldalhoz.');
}
