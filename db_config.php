<?php
// api/db_config.php

$host = 'localhost';
// ✅ NOMBRE CORREGIDO
$dbname = 'magic_letters_db'; 
$username = 'root';
$password = '1998'; // En XAMPP suele ser vacío

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Si falla, devolvemos un JSON claro en lugar de romper la página
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión a la Base de Datos: " . $e->getMessage()]);
    exit;
}
?>