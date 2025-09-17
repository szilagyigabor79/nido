<?php
require __DIR__.'/../config.php';
$email = 'admin@nidoingatlan.hu';
$pass  = '1_pun%CIZIvatar12';
$hash = password_hash($pass, PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (email, pass_hash) VALUES (?,?)")->execute([$email,$hash]);
echo "OK";
