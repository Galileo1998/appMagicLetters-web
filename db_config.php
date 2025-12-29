<?php
// db_config.php
$host = "localhost";
$db_name = "magic_letters_db";
$username = "root"; // Tu usuario de MySQL
$password = "1998";     // Tu contraseña de MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}