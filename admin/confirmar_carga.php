<?php
session_start();
require '../db_config.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['json_paquete'])) {
    
    $datos = json_decode($_POST['json_paquete'], true);

    if (!$datos || count($datos) === 0) {
        die("Error: No se recibieron datos válidos.");
    }

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO letters (
                    local_id,
                    slip_id, 
                    child_code, 
                    child_name, 
                    village, 
                    due_date, 
                    sex, 
                    birthdate, 
                    contact_id, 
                    contact_name, 
                    ia_id,
                    status,
                    created_at
                ) VALUES (
                    NULL, 
                    :slip_id, 
                    :child_code, 
                    :child_name, 
                    :village, 
                    :due_date, 
                    :sex, 
                    :birthdate, 
                    :contact_id, 
                    :contact_name, 
                    :ia_id,
                    'ASSIGNED', 
                    NOW()
                ) ON DUPLICATE KEY UPDATE 
                    child_name = VALUES(child_name),
                    due_date   = VALUES(due_date),
                    updated_at = NOW()";

        $stmt = $pdo->prepare($sql);

        foreach ($datos as $row) {
            // Limpiamos y recortamos los datos a 250 caracteres por seguridad
            $stmt->execute([
                ':slip_id'      => trim($row['slip_id']),
                ':child_code'   => trim($row['child_nbr']),
                ':child_name'   => substr(trim($row['child_name']), 0, 250),
                ':village'      => substr(trim($row['village']), 0, 250),
                ':due_date'     => trim($row['due_date']),
                ':sex'          => trim($row['sex']),
                ':birthdate'    => trim($row['birthdate']),
                ':contact_id'   => trim($row['contact_id']),
                ':contact_name' => substr(trim($row['contact_name']), 0, 250), // 👈 Recorte preventivo
                ':ia_id'        => trim($row['ia_id'])
            ]);
        }

        $pdo->commit();
        
        header("Location: revisar_carga.php?status=success&count=" . count($datos));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<h3>Error al guardar en la base de datos:</h3>";
        echo "<p style='color:red;'>" . $e->getMessage() . "</p>";
        echo "<a href='revisar_carga.php'>Volver a intentar</a>";
        exit;
    }
} else {
    header("Location: cargar_pdf.php");
    exit;
}