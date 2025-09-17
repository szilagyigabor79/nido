<?php
require __DIR__.'/../config.php';
session_start();

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

$st = $pdo->prepare("SELECT id,email,pass_hash,role,is_active FROM users WHERE email=? LIMIT 1");
$st->execute([$email]);
$u = $st->fetch();

if (!$u || !$u['is_active'] || !password_verify($pass, $u['pass_hash'])) {
  http_response_code(401);
  exit('Hibás belépési adatok');
}

$_SESSION['uid']  = (int)$u['id'];
$_SESSION['role'] = $u['role'];
$_SESSION['email']= $u['email'];

header('Location: /nido/admin/ingatlanok.php'); 

exit;

