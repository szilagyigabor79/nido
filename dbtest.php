<?php
try {
  new PDO('mysql:host=localhost;port=3306;dbname=igngatlan_db;charset=utf8mb4','root','kafferBIValy');
  echo "OK – kapcsolódik.";
} catch (Throwable $e) { echo "HIBA: ".$e->getMessage(); }
