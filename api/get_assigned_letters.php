<?php
// Habilitar reporte de errores para ver qué está pasando
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

// Verificar si existe el archivo de configuración
if (!file_exists('../db_config.php')) {
    http_response_code(500);
    echo json_encode(["error" => "No se encuentra db_config.php"]);
    exit;
}

require '../db_config.php';

// 1. Recibir JSON
$input = json_decode(file_get_contents("php://input"), true);
// Si estás probando desde el navegador, usa un teléfono de prueba fijo
$phone = $input['phone'] ?? $_GET['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(["error" => "Teléfono no recibido."]);
    exit;
}

try {
    // 2. BUSCAR TÉCNICO
    // ⚠️ VERIFICA EN PHPMYADMIN: ¿Tu tabla de usuarios se llama 'users'?
    $stmt = $pdo->prepare("SELECT id FROM technicians WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Si no encuentra al técnico, devolvemos array vacío (no es error, es que no existe)
        echo json_encode(["error" => "Usuario no encontrado con tel: $phone"]);
        exit;
    }

    $userId = $user['id'];

    // 3. BUSCAR CARTAS
    // ⚠️ VERIFICA: ¿La columna que vincula al técnico se llama 'assigned_to'?
    $sql = "SELECT * FROM letters 
            WHERE tech_id = ? 
            AND status IN ('ASSIGNED', 'RETURNED') 
            ORDER BY status DESC, due_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $letters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($letters);

} catch (Exception $e) {
    // AQUÍ ESTÁ LA CLAVE: Devolvemos el mensaje exacto del error SQL
    http_response_code(500);
    echo json_encode(["error" => "Error SQL: " . $e->getMessage()]);
}
?>