<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../db_config.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rows'])) {
    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO letters (
                    local_id, 
                    slip_id, 
                    child_nbr,
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
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', NOW()
                )";
        
        $stmt = $pdo->prepare($sql);
        $contador = 0;

        foreach ($_POST['rows'] as $row) {
            if (empty($row['slip_id'])) continue;

            $local_id = "L" . time() . "_" . rand(1000, 9999) . "_" . $contador;
            
            // --- PROTECCIÓN CONTRA TEXTO LARGO (La Solución) ---
            // Usamos substr($texto, 0, X) para cortar al límite X caracteres
            
            $slip_id      = substr(trim($row['slip_id']), 0, 50);
            $child_nbr    = substr(trim($row['child_nbr']), 0, 50); 
            $child_code   = substr(trim($row['child_nbr']), 0, 50); // Reusamos nbr
            
            // Nombres y textos largos: Cortamos a 250 caracteres por seguridad
            $child_name   = substr(trim($row['child_name']), 0, 250);
            $village      = substr(trim($row['village']), 0, 150);
            $due_date     = substr(trim($row['due_date']), 0, 50);
            
            $sex          = substr(trim($row['sex'] ?? ''), 0, 10);
            $birthdate    = substr(trim($row['birthdate'] ?? ''), 0, 50);
            $contact_id   = substr(trim($row['contact_id'] ?? ''), 0, 50);
            
            // AQUÍ ESTABA EL ERROR: Cortamos el nombre del patrocinador a 250 letras máx
            $contact_name = substr(trim($row['contact_name'] ?? ''), 0, 250);
            
            $ia_id        = substr(trim($row['ia_id'] ?? ''), 0, 50);

            $stmt->execute([
                $local_id,
                $slip_id,
                $child_nbr,
                $child_code,
                $child_name,
                $village,
                $due_date,
                $sex,
                $birthdate,
                $contact_id,
                $contact_name,
                $ia_id
            ]);
            $contador++;
        }

        $pdo->commit();
        
        // Mensaje de éxito
        echo "<div style='font-family:sans-serif; padding:20px; color:green; background:#e8f5e9; border:1px solid green; max-width:600px; margin:20px auto; border-radius:8px; text-align:center;'>
                <h1>✅ ¡Guardado Correctamente!</h1>
                <p>Se procesaron <strong>$contador</strong> registros.</p>
                <p>El error de longitud ha sido corregido automáticamente.</p>
                <br>
                <a href='revisar_carga.php' style='text-decoration:none; background:#28a745; color:white; padding:12px 25px; border-radius:5px; font-weight:bold;'>Cargar otro PDF</a>
              </div>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        
        // Mensaje de error amigable
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'Data too long') !== false) {
             $errorMsg = "Aún hay un dato demasiado largo. Intenta ampliar las columnas en tu base de datos a VARCHAR(255).";
        }
        
        die("<div style='font-family:sans-serif; padding:20px; color:red; border:1px solid red; background:#fff5f5; margin:20px;'>
                <h2>Error al guardar</h2>
                <p>$errorMsg</p>
             </div>");
    }
} else {
    header("Location: revisar_carga.php");
    exit;
}