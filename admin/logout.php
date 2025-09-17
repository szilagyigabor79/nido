<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: /nido/admin/login.html');
exit;
