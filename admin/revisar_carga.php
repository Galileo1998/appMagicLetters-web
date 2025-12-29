<?php
session_start();
ini_set('display_errors', 0); // Ocultar errores feos de PHP al usuario
error_reporting(E_ALL);

require '../vendor/autoload.php';

$mensaje_error = "";
$mostrar_formulario = true;
$rows = [];

// =======================================================
// LÓGICA: ¿SE ENVIÓ UN ARCHIVO?
// =======================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    
    // 1. Chequeo de Errores de Subida
    if ($_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        try {
            // PROCESAR PDF
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($_FILES['pdf_file']['tmp_name']);
            $cleanText = preg_replace('/\s+/', ' ', $pdf->getText());

            // EXTRAER DATOS (Tu lógica probada)
            preg_match_all('/Slip Id:\s*(\d+)/i', $cleanText, $matches_slip);
            preg_match_all('/Child Name:\s*(.*?)(?=\s*Contact Id:|\s*Age:|\s*Gender:|$)/i', $cleanText, $matches_name);
            preg_match_all('/Child Nbr:\s*(\d+)/i', $cleanText, $matches_nbr);
            preg_match_all('/Village:\s*(.*?)(?=\s*Child Name:|\s*Case:|$)/i', $cleanText, $matches_village);
            preg_match_all('/Due Date:\s*([\d\-A-Za-z]+)/i', $cleanText, $matches_date);
            
            // Campos Extra
            preg_match_all('/Sex:\s*([MF])/i', $cleanText, $matches_sex);
            preg_match_all('/Birthdate:\s*([\d\-A-Za-z]+)/i', $cleanText, $matches_birth);
            preg_match_all('/Contact Id:\s*(\d+)/i', $cleanText, $matches_cid);
            preg_match_all('/Contact Name:\s*(.*?)(?=\s*Case:|\s*Child Nbr:|$)/i', $cleanText, $matches_cname);
            preg_match_all('/IA ID:\s*(.*?)(?=\s*Contact Name:|\s*-\s*USA|$)/i', $cleanText, $matches_ia);

            $slips = $matches_slip[1];
            $names = $matches_name[1];
            $nbrs  = $matches_nbr[1];
            $villages = $matches_village[1];
            $dates = $matches_date[1];
            $sexes = $matches_sex[1];
            $births = $matches_birth[1];
            $cids = $matches_cid[1];
            $cnames = $matches_cname[1];
            $ias = $matches_ia[1];

            $count = count($slips);
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'slip_id'    => trim($slips[$i]),
                    'child_nbr'  => trim($nbrs[$i] ?? 'S/N'),
                    'child_name' => trim($names[$i] ?? 'No detectado'),
                    'village'    => trim($villages[$i] ?? 'No detectada'),
                    'due_date'   => trim($dates[$i] ?? 'Sin fecha'),
                    'sex'        => trim($sexes[$i] ?? ''),
                    'birthdate'  => trim($births[$i] ?? ''),
                    'contact_id' => trim($cids[$i] ?? ''),
                    'contact_name'=> trim($cnames[$i] ?? ''),
                    'ia_id'      => trim($ias[$i] ?? '')
                ];
            }

            // Si llegamos aquí, todo salió bien: Ocultamos el form de carga y mostramos la tabla
            $mostrar_formulario = false;

        } catch (Exception $e) {
            $mensaje_error = "Error al leer el PDF: " . $e->getMessage();
        }
    } elseif ($_FILES['pdf_file']['error'] === UPLOAD_ERR_INI_SIZE) {
        $mensaje_error = "⚠️ El archivo es demasiado grande. El servidor no lo aceptó.";
    } else {
        $mensaje_error = "⚠️ Error en la subida (Código: " . $_FILES['pdf_file']['error'] . "). Intenta de nuevo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar PDF - MagicLetter</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

        /* Estilos del Formulario de Carga */
        .upload-card { background: white; padding: 50px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); text-align: center; max-width: 500px; margin: 40px auto; }
        .upload-icon { font-size: 50px; color: #1e62d0; margin-bottom: 20px; }
        .btn-upload { background: #1e62d0; color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; margin-top: 20px; width: 100%; transition: background 0.3s; }
        .btn-upload:hover { background: #164ba0; }
        .error-msg { background: #ffe6e6; color: #cc0000; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; }

        /* Estilos de la Tabla de Revisión */
        .review-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .table-wrapper { overflow-x: auto; max-height: 70vh; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 13px; }
        th { background: #1e62d0; color: white; padding: 10px; text-align: left; position: sticky; top: 0; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        input[type="text"] { width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-confirm { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; float: right; margin-top: 20px; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container">
        
        <?php if ($mensaje_error): ?>
            <div class="error-msg">
                <?= $mensaje_error ?>
            </div>
        <?php endif; ?>

        <?php if ($mostrar_formulario): ?>
            <div class="upload-card">
                <div class="upload-icon">📄</div>
                <h2 style="color:#333;">Cargar Nuevo PDF</h2>
                <p style="color:#666;">Sube el archivo de boletas para procesar.</p>

                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="file" name="pdf_file" accept=".pdf" required style="margin-top:20px;">
                    <br>
                    <button type="submit" class="btn-upload">Analizar PDF</button>
                </form>
            </div>

        <?php else: ?>
            <div class="review-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 style="color:#1e62d0; margin:0;">Revisión de Datos</h2>
                    <a href="revisar_carga.php" style="color:#666; text-decoration:none;">❌ Cancelar</a>
                </div>
                <p>Se detectaron <strong><?= count($rows) ?></strong> registros. Verifica antes de guardar.</p>

                <form id="mainForm" action="confirmar_carga.php" method="POST">
                    <input type="hidden" name="json_paquete" id="json_paquete">

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Slip ID</th>
                                    <th>N° Niño</th>
                                    <th>Nombre</th>
                                    <th>Comunidad</th>
                                    <th>Patrocinador</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $i => $r): ?>
                                <tr class="data-row">
                                    <td><input type="text" class="d-slip" value="<?= htmlspecialchars($r['slip_id']) ?>"></td>
                                    <td><input type="text" class="d-nbr" value="<?= htmlspecialchars($r['child_nbr']) ?>"></td>
                                    <td><input type="text" class="d-name" value="<?= htmlspecialchars($r['child_name']) ?>"></td>
                                    <td><input type="text" class="d-village" value="<?= htmlspecialchars($r['village']) ?>"></td>
                                    <td><input type="text" class="d-cname" value="<?= htmlspecialchars($r['contact_name']) ?>"></td>
                                    <td><input type="text" class="d-date" value="<?= htmlspecialchars($r['due_date']) ?>"></td>
                                    
                                    <input type="hidden" class="d-sex" value="<?= htmlspecialchars($r['sex']) ?>">
                                    <input type="hidden" class="d-birth" value="<?= htmlspecialchars($r['birthdate']) ?>">
                                    <input type="hidden" class="d-cid" value="<?= htmlspecialchars($r['contact_id']) ?>">
                                    <input type="hidden" class="d-ia" value="<?= htmlspecialchars($r['ia_id']) ?>">
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" onclick="enviarDatos()" class="btn-confirm">Guardar Todo</button>
                </form>
            </div>

            <script>
                function enviarDatos() {
                    let filas = document.querySelectorAll('.data-row');
                    let datos = [];
                    filas.forEach(row => {
                        datos.push({
                            slip_id:      row.querySelector('.d-slip').value,
                            child_nbr:    row.querySelector('.d-nbr').value,
                            child_name:   row.querySelector('.d-name').value,
                            village:      row.querySelector('.d-village').value,
                            contact_name: row.querySelector('.d-cname').value,
                            due_date:     row.querySelector('.d-date').value,
                            sex:          row.querySelector('.d-sex').value,
                            birthdate:    row.querySelector('.d-birth').value,
                            contact_id:   row.querySelector('.d-cid').value,
                            ia_id:        row.querySelector('.d-ia').value
                        });
                    });
                    document.getElementById('json_paquete').value = JSON.stringify(datos);
                    document.getElementById('mainForm').submit();
                }
            </script>
        <?php endif; ?>

    </div>
</body>
</html>