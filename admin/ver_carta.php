<?php
include '../db_config.php';

if (!isset($_GET['id'])) {
    die("ID de carta no proporcionado.");
}

$local_id = $_GET['id'];

// 1. Obtener datos de la carta
$stmt = $pdo->prepare("SELECT l.*, u.nombre as tecnico_nombre 
                        FROM letters l 
                        LEFT JOIN usuarios u ON l.assigned_to_user_id = u.id 
                        WHERE l.local_id = ?");
$stmt->execute([$local_id]);
$carta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$carta) {
    die("La carta no existe en el sistema.");
}

// 2. Obtener el dibujo (Strokes JSON)
$stmtD = $pdo->prepare("SELECT svg_xml FROM drawings WHERE letter_id = ?");
$stmtD->execute([$local_id]);
$dibujo = $stmtD->fetch(PDO::FETCH_ASSOC);

// 3. Obtener la foto (si existe)
$stmtP = $pdo->prepare("SELECT photo_url FROM photos WHERE letter_id = ?");
$stmtP->execute([$local_id]);
$foto = $stmtP->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle: <?= htmlspecialchars($carta['child_code']) ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 40px; color: #333; }
        .card { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 1.2rem; font-weight: bold; color: #1e62d0; margin-bottom: 10px; display: block; border-left: 4px solid #1e62d0; padding-left: 10px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Contenedor del Dibujo */
        #canvas-wrapper { 
            background: #fff; 
            border: 1px solid #ddd; 
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            justify-content: center;
        }
        canvas { background: white; cursor: default; max-width: 100%; height: auto; }

        .foto-evidencia { width: 100%; border-radius: 8px; border: 1px solid #ddd; }
        .btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
        .btn-back:hover { color: #000; }
        .text-box { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; line-height: 1.6; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="card">
    <div class="header">
        <div>
            <a href="admin_panel.php" class="btn-back">← Volver al panel</a>
            <h1 style="margin: 10px 0 0 0;">Niño: <?= htmlspecialchars($carta['child_code']) ?></h1>
        </div>
        <div style="text-align: right;">
            <span style="display:block; font-weight:bold; color:#28a745;">ESTADO: <?= $carta['status'] ?></span>
            <small>Técnico: <?= htmlspecialchars($carta['tecnico_nombre']) ?></small>
        </div>
    </div>

    <div class="section">
        <span class="section-title">Sentimientos / Mensaje</span>
        <div class="text-box">
            <?= nl2br(htmlspecialchars($carta['text_feelings'] ?? 'No se ingresó texto.')) ?>
        </div>
    </div>

    <div class="grid">
        <div class="section">
            <span class="section-title">Dibujo Realizado</span>
            <div id="canvas-wrapper">
                <canvas id="dibujoCanvas" width="500" height="500"></canvas>
            </div>
        </div>

        <div class="section">
            <span class="section-title">Foto de la Carta Física</span>
            <?php if ($foto): ?>
                <img src="../<?= htmlspecialchars($foto['photo_url']) ?>" class="foto-evidencia" alt="Foto evidencia">
            <?php else: ?>
                <div class="text-box" style="text-align:center; padding: 50px 0;">
                    <p style="color:#999;">No hay foto disponible.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Recuperamos los trazos enviados por la APP (JSON)
    const rawData = `<?= $dibujo['svg_xml'] ?? '[]' ?>`;
    
    try {
        const strokes = JSON.parse(rawData);
        const canvas = document.getElementById('dibujoCanvas');
        const ctx = canvas.getContext('2d');

        // Función para dibujar cada trazo
        function drawStroke(stroke) {
            if (!stroke.d) return;

            // Path2D entiende el formato SVG 'd' que envía la librería de dibujo
            const path = new Path2D(stroke.d);
            
            ctx.strokeStyle = stroke.color || "#000000";
            ctx.lineWidth = stroke.width || 2;
            ctx.lineCap = "round";
            ctx.lineJoin = "round";
            
            ctx.stroke(path);
        }

        // Dibujar todos los trazos recuperados
        if (Array.isArray(strokes)) {
            strokes.forEach(drawStroke);
        }
    } catch (e) {
        console.error("Error al renderizar el dibujo:", e);
    }
</script>

</body>
</html>