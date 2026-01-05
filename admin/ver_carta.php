<?php
session_start();

// 1. SEGURIDAD ADMIN
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

require '../db_config.php';

// 2. VALIDAR ID CARTA
if (!isset($_GET['id'])) {
    header("Location: admin_panel.php");
    exit;
}

$letter_id = $_GET['id'];
$mensaje_error = '';
$mensaje_exito = '';

// --- LÓGICA: DEVOLVER CARTA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    $reason = trim($_POST['return_reason']);
    if (!empty($reason)) {
        $stmt = $pdo->prepare("UPDATE letters SET status = 'RETURNED', return_reason = ? WHERE id = ?");
        if ($stmt->execute([$reason, $letter_id])) {
            echo "<script>alert('Carta devuelta correctamente.'); window.location.href='admin_panel.php';</script>";
            exit;
        }
    }
}

// --- LÓGICA: ELIMINAR ADJUNTO (NUEVO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attachment') {
    $att_id = $_POST['attachment_id'];
    
    // 1. Obtener ruta del archivo para borrarlo del disco
    $stmt_get = $pdo->prepare("SELECT file_path FROM letter_attachments WHERE id = ?");
    $stmt_get->execute([$att_id]);
    $file = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        $full_path = '../' . $file['file_path']; // Ajustar ruta relativa
        
        // 2. Borrar archivo físico
        if (file_exists($full_path)) {
            unlink($full_path);
        }

        // 3. Borrar registro DB
        $stmt_del = $pdo->prepare("DELETE FROM letter_attachments WHERE id = ?");
        $stmt_del->execute([$att_id]);
        
        $mensaje_exito = "Archivo eliminado correctamente.";
    }
}

// 3. CONSULTA PRINCIPAL
$sql = "SELECT l.*, t.full_name as tech_name 
        FROM letters l 
        LEFT JOIN technicians t ON l.tech_id = t.id 
        WHERE l.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$letter_id]);
$carta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$carta) { die("Carta no encontrada."); }

// 4. BUSCAR ADJUNTOS (Guardamos ID y Path para poder borrar)
$sql_att = "SELECT * FROM letter_attachments WHERE letter_id = ?";
$stmt_att = $pdo->prepare($sql_att);
$stmt_att->execute([$letter_id]);
$adjuntos = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

$dibujo_data = null; // Guardará array completo (id, path)
$fotos_data = [];    // Guardará array de arrays

foreach ($adjuntos as $adj) {
    if ($adj['file_type'] == 'DRAWING') $dibujo_data = $adj;
    if ($adj['file_type'] == 'PHOTO')   $fotos_data[] = $adj;
}

$mensaje_real = $carta['final_message'] ?? ''; 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión Admin - MagicLetter</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --color-primary: #46B094; --color-support: #34859B;
            --color-bg: #f4f7f6; --color-text: #444; --color-danger: #dc3545;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); margin: 0; padding: 20px; color: var(--color-text); }
        .container { max-width: 1200px; margin: auto; }

        /* UI NAVEGACIÓN */
        .admin-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn { text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-back { color: #666; background: #eee; }
        .btn-print { background: var(--color-support); color: white; }
        .btn-return { background: var(--color-danger); color: white; }

        /* LAYOUT */
        .layout-grid { display: grid; grid-template-columns: 350px 1fr; gap: 25px; align-items: start; }
        .meta-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid var(--color-primary); }
        .meta-title { font-size: 14px; font-weight: 800; color: var(--color-primary); margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .meta-row { margin-bottom: 15px; } .meta-label { font-size: 11px; color: #888; font-weight: 700; display: block; } .meta-value { font-size: 15px; font-weight: 600; }
        
        /* HOJA CARTA */
        .letter-paper { background: white; min-height: 800px; padding: 40px; box-shadow: 0 0 30px rgba(0,0,0,0.1); }
        .print-header { border-bottom: 2px solid var(--color-primary); padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .ph-brand { font-size: 24px; font-weight: 800; color: var(--color-primary); } .ph-child { font-size: 18px; font-weight: 700; margin: 0; }
        .letter-body { font-family: 'Georgia', serif; font-size: 16px; line-height: 1.6; color: #222; margin-bottom: 40px; white-space: pre-line; }
        
        /* --- ESTILOS DE IMÁGENES Y BOTONES DE ACCIÓN (NUEVO) --- */
        .images-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .img-container { 
            position: relative; border: 1px solid #eee; padding: 5px; 
            background: #fafafa; border-radius: 4px; break-inside: avoid;
        }
        .img-container:hover .img-actions { opacity: 1; } /* Mostrar botones al pasar mouse */

        .img-container img { width: 100%; height: auto; display: block; border-radius: 2px; }
        
        .img-caption { font-size: 10px; color: #999; text-align: center; margin-top: 5px; font-family: sans-serif; }

        /* Botones Flotantes sobre la imagen */
        .img-actions {
            position: absolute; top: 10px; right: 10px; display: flex; gap: 5px;
            opacity: 0; transition: opacity 0.2s; /* Ocultos por defecto para limpieza */
            background: rgba(255,255,255,0.9); padding: 5px; border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        /* Para móvil o pantallas táctiles, siempre visibles o usar otro método */
        @media (hover: none) { .img-actions { opacity: 1; } }

        .btn-icon-mini {
            width: 28px; height: 28px; border-radius: 50%; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 12px;
            text-decoration: none; color: white; transition: transform 0.2s;
        }
        .btn-icon-mini:hover { transform: scale(1.15); }
        .btn-dl { background: var(--color-support); }
        .btn-del { background: var(--color-danger); }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { background: white; margin: 15% auto; padding: 25px; border-radius: 10px; width: 400px; border-left: 5px solid var(--color-danger); }
        textarea { width: 100%; height: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; margin: 10px 0; }

        /* IMPRESIÓN LIMPIA */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .admin-toolbar, .meta-card, .btn, .modal, .img-actions { display: none !important; } /* Ocultar botones */
            .container { max-width: 100%; } .layout-grid { display: block; }
            .letter-paper { box-shadow: none; padding: 20px; width: 100%; }
            .img-container { border: none; padding: 0; margin-bottom: 20px; max-width: 60%; margin-left: auto; margin-right: auto; }
        }
    </style>
</head>
<body>

    <div class="admin-toolbar">
        <a href="admin_panel.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        
        <div style="display:flex; gap:10px;">
            <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> PDF / Imprimir</button>
            <?php if(in_array($carta['status'], ['SYNCED', 'RETURNED', 'COMPLETADO'])): ?>
                <button onclick="abrirModal()" class="btn btn-return"><i class="fa-solid fa-ban"></i> Devolver</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <div class="layout-grid">
            
            <div class="meta-card">
                <div class="meta-title">Datos Administrativos</div>
                <div class="meta-row"><span class="meta-label">Estado</span><strong style="color:var(--color-primary);"><?= $carta['status'] ?></strong></div>
                <div class="meta-row"><span class="meta-label">Técnico</span><span class="meta-value"><?= htmlspecialchars($carta['tech_name'] ?? '--') ?></span></div>
                <div style="margin-top:20px; text-align:center;"><svg id="barcode"></svg><div style="font-size:10px; color:#aaa;"><?= $carta['slip_id'] ?></div></div>
            </div>

            <div class="letter-paper">
                <div class="print-header">
                    <div><div class="ph-brand">MagicLetter</div><div style="font-size:12px; color:#666;">Documento Digital</div></div>
                    <div style="text-align:right;">
                        <h2 class="ph-child"><?= htmlspecialchars($carta['child_name']) ?></h2>
                        <div style="font-size:14px; font-family:monospace;">ID: <?= htmlspecialchars($carta['child_nbr'] ?: $carta['child_code']) ?></div>
                    </div>
                </div>

                <div style="font-weight:bold; color:var(--color-support); border-bottom:1px solid #ddd; margin-bottom:15px;">CONTENIDO</div>
                <div class="letter-body">
                    <?= !empty($mensaje_real) ? nl2br(htmlspecialchars($mensaje_real)) : '<i style="color:#ccc;">[Sin texto]</i>' ?>
                </div>

                <div style="font-weight:bold; color:var(--color-support); border-bottom:1px solid #ddd; margin-bottom:15px; margin-top:30px;">ADJUNTOS</div>
                
                <div class="images-grid">
                    
                    <?php if($dibujo_data): ?>
                    <div class="img-container">
                        <img src="../<?= htmlspecialchars($dibujo_data['file_path']) ?>" alt="Dibujo">
                        <div class="img-caption">DIBUJO</div>
                        
                        <div class="img-actions">
                            <a href="../<?= htmlspecialchars($dibujo_data['file_path']) ?>" download class="btn-icon-mini btn-dl" title="Descargar">
                                <i class="fa-solid fa-download"></i>
                            </a>
                            <form method="POST" onsubmit="return confirm('¿Estás seguro de ELIMINAR este dibujo permanentemente?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete_attachment">
                                <input type="hidden" name="attachment_id" value="<?= $dibujo_data['id'] ?>">
                                <button type="submit" class="btn-icon-mini btn-del" title="Eliminar">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php foreach($fotos_data as $i => $foto): ?>
                    <div class="img-container">
                        <img src="../<?= htmlspecialchars($foto['file_path']) ?>" alt="Foto">
                        <div class="img-caption">FOTOGRAFÍA <?= $i + 1 ?></div>

                        <div class="img-actions">
                            <a href="../<?= htmlspecialchars($foto['file_path']) ?>" download class="btn-icon-mini btn-dl" title="Descargar">
                                <i class="fa-solid fa-download"></i>
                            </a>
                            <form method="POST" onsubmit="return confirm('¿Estás seguro de ELIMINAR esta foto?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete_attachment">
                                <input type="hidden" name="attachment_id" value="<?= $foto['id'] ?>">
                                <button type="submit" class="btn-icon-mini btn-del" title="Eliminar">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </div>

    <div id="modalReturn" class="modal">
        <div class="modal-content">
            <h3>Devolver Carta</h3>
            <form method="POST">
                <input type="hidden" name="action" value="return">
                <textarea name="return_reason" placeholder="Motivo..." required></textarea>
                <div style="text-align:right;">
                    <button type="button" onclick="cerrarModal()" class="btn" style="background:#eee;">Cancelar</button>
                    <button type="submit" class="btn btn-return">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        JsBarcode("#barcode", "<?= htmlspecialchars($carta['slip_id']) ?>", { format: "CODE128", lineColor: "#333", width: 2, height: 40, displayValue: false });
        const modal = document.getElementById('modalReturn');
        function abrirModal() { modal.style.display = "block"; }
        function cerrarModal() { modal.style.display = "none"; }
        window.onclick = function(e) { if(e.target == modal) cerrarModal(); }
    </script>
</body>
</html>