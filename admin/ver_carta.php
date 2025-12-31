<?php
// admin/ver_carta.php
require '../db_config.php';

if (!isset($_GET['id'])) die("Error: ID no proporcionado.");
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM letters WHERE id = ?");
$stmt->execute([$id]);
$letter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$letter) die("Carta no encontrada.");

$stmt = $pdo->prepare("SELECT * FROM letter_attachments WHERE letter_id = ?");
$stmt->execute([$letter['id']]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$photos = []; 
$drawing = null;

// Definimos el child_code para usarlo en descargas y mostrarlo
// Si la columna en la BD se llama 'child_code', úsala. Si no, usa un respaldo.
$childCode = $letter['child_code'] ?? 'SINCÓDIGO';

foreach ($attachments as $att) {
    $path = "../" . $att['file_path'];
    if ($att['file_type'] === 'DRAWING') {
        $drawing = $path;
    } else {
        $photos[] = ['id' => $att['id'], 'path' => $path];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión Carta <?php echo $childCode; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; }
        .card { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        /* Estado estilo etiqueta */
        .status { padding: 6px 15px; border-radius: 20px; color: white; font-weight: bold; float: right; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px; }
        
        h1 { margin-top: 0; color: #333; }
        .meta-info { color: #666; font-size: 1.05em; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .meta-info span { margin-right: 15px; }
        
        .msg { background: #fffbe6; padding: 20px; border-radius: 8px; border: 1px solid #ffe58f; white-space: pre-wrap; margin: 20px 0; font-size: 1.1em; line-height: 1.6; color: #444; }
        
        .img-grid { display: flex; flex-wrap: wrap; gap: 15px; }
        .photo-container { position: relative; width: 48%; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .photo-container img { width: 100%; display: block; }
        
        /* Botones flotantes mejorados */
        .btn-float {
            position: absolute; top: 10px; width: 35px; height: 35px; border-radius: 50%; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.3); text-decoration: none; font-size: 18px; color: white; transition: transform 0.2s;
        }
        .btn-float:hover { transform: scale(1.1); }
        .btn-delete { right: 10px; background: #dc3545; }
        .btn-download { right: 55px; background: #007bff; }

        .actions { margin-top: 30px; text-align: right; border-top: 1px solid #eee; padding-top: 20px; }
        .btn { padding: 12px 24px; border-radius: 6px; cursor: pointer; border: none; font-weight: bold; font-size: 14px; margin-left: 10px; transition: background 0.3s; }
        .btn-ret { background: #fff; color: #dc3545; border: 2px solid #dc3545; }
        .btn-ret:hover { background: #dc3545; color: white; }
        .btn-print { background: #333; color: white; }
        .btn-print:hover { background: #000; }
    </style>
</head>
<body>
    <div class="card">
        <span class="status" style="background: <?php echo $letter['status']=='RETURNED' ? '#dc3545' : '#28a745'; ?>">
            <?php echo $letter['status'] == 'RETURNED' ? 'DEVUELTA' : $letter['status']; ?>
        </span>

        <h1><?php echo htmlspecialchars($letter['child_name']); ?></h1>
        
        <div class="meta-info">
            <span>📍 <b>Comunidad:</b> <?php echo $letter['village']; ?></span>
            <span>🆔 <b>Child Code:</b> <?php echo $childCode; ?></span>
            <span>📄 <b>Slip ID:</b> #<?php echo $letter['slip_id']; ?></span>
        </div>
        
        <h3>📝 Mensaje Final:</h3>
        <div class="msg"><?php echo nl2br(htmlspecialchars($letter['final_message'])); ?></div>

        <h3>🎨 Dibujo:</h3>
        <?php if($drawing): ?>
            <div class="photo-container" style="width: 100%;">
                <img src="<?php echo $drawing; ?>">
                <a href="<?php echo $drawing; ?>" download="<?php echo $childCode; ?>_0_dibujo.jpg" class="btn-float btn-download" title="Descargar">⬇</a>
            </div>
        <?php else: ?>
            <p style="color:#999; font-style:italic;">No hay dibujo disponible.</p>
        <?php endif; ?>

        <h3>📸 Fotos Adjuntas:</h3>
        <div class="img-grid">
            <?php 
            $i = 1; 
            foreach($photos as $p): 
                $downloadName = $childCode . "_" . $i . ".jpg";
            ?>
                <div class="photo-container" id="photo-<?php echo $p['id']; ?>">
                    <img src="<?php echo $p['path']; ?>">
                    
                    <a href="<?php echo $p['path']; ?>" download="<?php echo $downloadName; ?>" class="btn-float btn-download">⬇</a>

                    <button class="btn-float btn-delete" onclick="deletePhoto(<?php echo $p['id']; ?>)">✕</button>
                </div>
            <?php $i++; endforeach; ?>
            
            <?php if(empty($photos)): ?>
                <p style="color:#999; font-style:italic; width:100%;">No hay fotos adjuntas.</p>
            <?php endif; ?>
        </div>

        <div class="actions">
            <button onclick="ret()" class="btn btn-ret">🚫 Devolver Carta</button>
            <button onclick="window.print()" class="btn btn-print">🖨️ Imprimir</button>
        </div>
    </div>

    <script>
    async function deletePhoto(photoId) {
        if (!confirm("¿Seguro que quieres borrar esta foto permanentemente?")) return;
        try {
            const res = await fetch('../api/delete_attachment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: photoId })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('photo-' + photoId).remove();
            } else {
                alert("Error: " + (data.error || 'No se pudo borrar'));
            }
        } catch (e) {
            console.error(e);
            alert("Error de conexión");
        }
    }

    function ret() {
        let r = prompt("Escribe la razón de la devolución para el técnico:");
        if (r) {
            fetch('../api/return_letter.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: <?php echo $letter['id']; ?>, reason: r })
            }).then(() => {
                alert("Carta devuelta correctamente.");
                window.location.reload();
            });
        }
    }
    </script>
</body>
</html>