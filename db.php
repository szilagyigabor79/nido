<?php
$conn = new mysqli("localhost", "root", "root", "ingatlan_db");
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}
?>
