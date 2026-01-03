<?php
session_start();

// VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión de admin, mandar al login principal
    header("Location: ../index.php");
    exit;
}

require '../db_config.php';

ini_set('display_errors', 0); 
error_reporting(E_ALL);

require '../vendor/autoload.php';

$mensaje_error = "";
$mostrar_formulario = true;
$rows = [];

// =======================================================
// LÓGICA PHP (PROCESAMIENTO PDF)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    
    if ($_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($_FILES['pdf_file']['tmp_name']);
            $cleanText = preg_replace('/\s+/', ' ', $pdf->getText());

            // --- EXTRACCIÓN DE DATOS (Mismo Regex) ---
            preg_match_all('/Slip Id:\s*(\d+)/i', $cleanText, $matches_slip);
            preg_match_all('/Child Name:\s*(.*?)(?=\s*Contact Id:|\s*Age:|\s*Gender:|$)/i', $cleanText, $matches_name);
            preg_match_all('/Child Nbr:\s*(\d+)/i', $cleanText, $matches_nbr);
            preg_match_all('/Village:\s*(.*?)(?=\s*Child Name:|\s*Case:|$)/i', $cleanText, $matches_village);
            preg_match_all('/Due Date:\s*([\d\-A-Za-z]+)/i', $cleanText, $matches_date);
            
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
                    'child_nbr'  => trim($nbrs[$i] ?? ''),
                    'child_name' => trim($names[$i] ?? ''),
                    'village'    => trim($villages[$i] ?? ''),
                    'due_date'   => trim($dates[$i] ?? ''),
                    'sex'        => trim($sexes[$i] ?? ''),
                    'birthdate'  => trim($births[$i] ?? ''),
                    'contact_id' => trim($cids[$i] ?? ''),
                    'contact_name'=> trim($cnames[$i] ?? ''),
                    'ia_id'      => trim($ias[$i] ?? '')
                ];
            }

            $mostrar_formulario = false;

        } catch (Exception $e) {
            $mensaje_error = "Error al leer el PDF: " . $e->getMessage();
        }
    } elseif ($_FILES['pdf_file']['error'] === UPLOAD_ERR_INI_SIZE) {
        $mensaje_error = "⚠️ El archivo es demasiado grande.";
    } else {
        $mensaje_error = "⚠️ Error en la subida.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar y Editar - MagicLetter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-primary: #46B094;
            --color-support: #34859B;
            --color-accent: #B4D6E0;
            --color-bg: #f4f7f6;
            --color-error: #dc3545;
        }

        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); margin: 0; color: #444; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

        /* Tarjeta de Carga */
        .upload-card { 
            background: white; padding: 60px 40px; border-radius: 12px; 
            box-shadow: 0 4px 25px rgba(0,0,0,0.08); text-align: center; 
            max-width: 550px; margin: 0 auto; 
            border-top: 6px solid var(--color-primary);
        }
        .file-input-container input[type="file"] {
            border: 2px dashed var(--color-accent); padding: 30px; width: 100%; border-radius: 8px; background: #fafafa; cursor: pointer;
        }
        .btn-upload { 
            background: var(--color-primary); color: white; padding: 14px 40px; border: none; border-radius: 30px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%; margin-top: 20px;
        }

        /* Tarjeta de Revisión */
        .review-card { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            border-top: 5px solid var(--color-support);
        }
        
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-cancel { color: #888; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn-cancel:hover { color: var(--color-error); }

        /* Tabla y Botones */
        .table-wrapper { overflow-x: auto; max-height: 65vh; margin-top: 10px; border-radius: 8px; border: 1px solid #eee; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; font-size: 13px; }
        th { background: var(--color-primary); color: white; padding: 15px; text-align: left; position: sticky; top: 0; z-index: 10; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; background: white; vertical-align: middle; }
        
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input[type="text"]:focus { outline: none; border-color: var(--color-primary); }

        /* Botones de Acción */
        .btn-row-action { 
            background: transparent; border: none; font-size: 16px; cursor: pointer; padding: 5px; 
            color: #888; transition: color 0.2s; 
        }
        .btn-delete:hover { color: var(--color-error); transform: scale(1.1); }

        .btn-add-row {
            background: var(--color-accent); color: var(--color-support); border: none; padding: 10px 20px; 
            border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 15px; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-add-row:hover { background: #9ecddb; }

        .btn-confirm { 
            background: var(--color-primary); color: white; padding: 12px 30px; 
            border: none; border-radius: 6px; cursor: pointer; font-weight: bold; 
            float: right; margin-top: 15px; 
        }
        .btn-confirm:hover { background: var(--color-support); }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container">
        
        <?php if ($mensaje_error): ?>
            <div style="background:#fff5f5; color:#dc3545; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; border:1px solid #ffc9c9;">
                <?= $mensaje_error ?>
            </div>
        <?php endif; ?>

        <?php if ($mostrar_formulario): ?>
            <div class="upload-card">
                <h2 style="color:var(--color-support);">Cargar Lote PDF</h2>
                <p style="color:#888;">Sube el PDF para extraer las cartas.</p>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="file-input-container">
                        <input type="file" name="pdf_file" accept=".pdf" required>
                    </div>
                    <button type="submit" class="btn-upload">Analizar PDF</button>
                </form>
            </div>

        <?php else: ?>
            <div class="review-card">
                <div class="review-header">
                    <div>
                        <h2 style="margin:0; color:var(--color-primary);">Revisión de Datos</h2>
                        <p style="margin-top:5px;">
                            Registros: <strong id="count-badge" style="color:var(--color-support);"><?= count($rows) ?></strong>
                        </p>
                    </div>
                    <a href="revisar_carga.php" class="btn-cancel">❌ Descartar</a>
                </div>

                <form id="mainForm" action="confirmar_carga.php" method="POST">
                    <input type="hidden" name="json_paquete" id="json_paquete">

                    <div class="table-wrapper">
                        <table id="dataTable">
                            <thead>
                                <tr>
                                    <th width="50"></th> <th width="10%">Slip ID</th>
                                    <th width="10%">N° Niño</th>
                                    <th width="25%">Nombre</th>
                                    <th width="20%">Comunidad</th>
                                    <th width="20%">Patrocinador</th>
                                    <th width="10%">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $i => $r): ?>
                                <tr class="data-row">
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-row-action btn-delete" onclick="eliminarFila(this)" title="Borrar fila">🗑️</button>
                                    </td>
                                    <td><input type="text" class="d-slip" value="<?= htmlspecialchars($r['slip_id']) ?>" style="font-weight:bold; color:var(--color-support);"></td>
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

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <button type="button" class="btn-add-row" onclick="agregarFila()">
                            <i class="fa-solid fa-plus-circle"></i> Agregar Fila Manual
                        </button>

                        <button type="button" onclick="enviarDatos()" class="btn-confirm">
                            <i class="fa-solid fa-check"></i> Guardar en Base de Datos
                        </button>
                    </div>
                </form>
            </div>

            <script>
                // 1. Eliminar Fila
                function eliminarFila(btn) {
                    if(confirm('¿Borrar esta fila?')) {
                        var row = btn.closest('tr');
                        row.remove();
                        actualizarContador();
                    }
                }

                // 2. Agregar Fila
                function agregarFila() {
                    const tbody = document.querySelector('#dataTable tbody');
                    const nuevaFila = `
                        <tr class="data-row">
                            <td style="text-align:center;">
                                <button type="button" class="btn-row-action btn-delete" onclick="eliminarFila(this)">🗑️</button>
                            </td>
                            <td><input type="text" class="d-slip" placeholder="Slip ID"></td>
                            <td><input type="text" class="d-nbr" placeholder="N° Niño"></td>
                            <td><input type="text" class="d-name" placeholder="Nombre"></td>
                            <td><input type="text" class="d-village" placeholder="Comunidad"></td>
                            <td><input type="text" class="d-cname" placeholder="Patrocinador"></td>
                            <td><input type="text" class="d-date" placeholder="Fecha"></td>
                            
                            <input type="hidden" class="d-sex" value="">
                            <input type="hidden" class="d-birth" value="">
                            <input type="hidden" class="d-cid" value="">
                            <input type="hidden" class="d-ia" value="">
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', nuevaFila);
                    actualizarContador();
                    
                    // Hacer scroll al final para ver la nueva fila
                    const tableWrapper = document.querySelector('.table-wrapper');
                    tableWrapper.scrollTop = tableWrapper.scrollHeight;
                }

                // 3. Actualizar Contador Visual
                function actualizarContador() {
                    const count = document.querySelectorAll('.data-row').length;
                    document.getElementById('count-badge').innerText = count;
                }

                // 4. Enviar Datos (Recopila también las filas agregadas manualmente)
                function enviarDatos() {
                    let filas = document.querySelectorAll('.data-row');
                    let datos = [];
                    
                    filas.forEach(row => {
                        // Solo guardar si al menos tiene Slip ID (para no guardar vacíos por error)
                        let slip = row.querySelector('.d-slip').value.trim();
                        if(slip) {
                            datos.push({
                                slip_id:      slip,
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
                        }
                    });
                    
                    if(datos.length === 0) {
                        alert("No hay datos válidos para guardar (Se requiere al menos el Slip ID).");
                        return;
                    }

                    if(confirm("¿Confirmas guardar " + datos.length + " cartas?")) {
                        document.getElementById('json_paquete').value = JSON.stringify(datos);
                        document.getElementById('mainForm').submit();
                    }
                }
            </script>
        <?php endif; ?>

    </div>
</body>
</html>