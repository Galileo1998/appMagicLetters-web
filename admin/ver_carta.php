<?php
// htdocs/magicletter/admin/ver_carta.php

// 1. CONEXIÓN (Usamos ../ porque estamos en la carpeta admin)
if (file_exists('../db_config.php')) {
    require '../db_config.php';
} else {
    die("Error: No se encuentra db_config.php. Asegúrate de estar en la carpeta correcta.");
}

// 2. Validar ID
if (!isset($_GET['id'])) {
    die("<h3 style='color:red'>Error: Falta el ID de la carta en la URL (ej: ?id=26)</h3>");
}
$id = $_GET['id'];

// 3. Obtener datos de la Carta
$stmt = $pdo->prepare("SELECT * FROM letters WHERE id = ?");
$stmt->execute([$id]);
$letter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$letter) {
    // Si no existe por ID, intentamos buscar por SLIP ID por si acaso
    $stmt = $pdo->prepare("SELECT * FROM letters WHERE slip_id = ?");
    $stmt->execute([$id]);
    $letter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$letter) {
        die("<h3 style='color:red'>La carta con ID $id no existe en la Base de Datos.</h3>");
    }
}

// 4. Obtener Adjuntos
$stmt = $pdo->prepare("SELECT * FROM letter_attachments WHERE letter_id = ?");
$stmt->execute([$letter['id']]); // Usamos el ID real encontrado
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$photos = [];
$drawing = null;

foreach ($attachments as $att) {
    // CORRECCIÓN DE RUTAS: Agregamos '../' para que el HTML encuentre la foto desde 'admin/'
    $fixedPath = "../" . $att['file_path']; 
    
    if ($att['file_type'] === 'DRAWING') {
        $drawing = $fixedPath;
    } else {
        $photos[] = $fixedPath;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta #<?php echo htmlspecialchars($letter['slip_id']); ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #eaeff2; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h1 { color: #1e62d0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 10px; }
        .info-item label { display: block; font-size: 11px; color: #888; font-weight: bold; text-transform: uppercase; }
        .info-item span { font-size: 16px; font-weight: 600; color: #333; }
        .section-title { font-size: 18px; color: #444; margin-top: 40px; margin-bottom: 10px; font-weight: bold; border-left: 4px solid #1e62d0; padding-left: 10px;}
        
        /* Mensaje */
        .message-box { background: #fffbe6; border: 1px solid #ffe58f; padding: 25px; border-radius: 8px; font-size: 18px; line-height: 1.6; white-space: pre-wrap; color: #555; }
        
        /* Fotos y Dibujo */
        .drawing-container { text-align: center; border: 2px dashed #ddd; padding: 10px; border-radius: 10px; background: #fff; }
        .drawing-img { max-width: 100%; height: auto; max-height: 400px; }
        .photos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .photo-card img { width: 100%; height: 200px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; }
        
        .btn-back { display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <a href="index.php" class="btn-back">← Volver al Panel</a>
    <div class="container">
        <h1><?php echo htmlspecialchars($letter['child_name']); ?></h1>

        <div class="info-grid">
            <div class="info-item"><label>ID</label><span>#<?php echo $letter['slip_id']; ?></span></div>
            <div class="info-item"><label>Comunidad</label><span><?php echo $letter['village']; ?></span></div>
            <div class="info-item"><label>Estado</label><span style="color:green"><?php echo $letter['status']; ?></span></div>
            <div class="info-item"><label>Fecha</label><span><?php echo date("d/m/Y", strtotime($letter['updated_at'])); ?></span></div>
        </div>

        <div class="section-title">Mensaje del Niño</div>
        <div class="message-box">
            <?php 
                if (!empty($letter['final_message'])) {
                    echo nl2br(htmlspecialchars($letter['final_message'])); 
                } else {
                    echo "<span style='color:red'>⚠️ El mensaje está vacío.</span>";
                }
            ?>
        </div>

        <div class="section-title">Dibujo</div>
        <div class="drawing-container">
            <?php if ($drawing): ?>
                <img src="<?php echo $drawing; ?>" class="drawing-img">
            <?php else: ?>
                <p>No hay dibujo.</p>
            <?php endif; ?>
        </div>

        <div class="section-title">Fotos</div>
        <div class="photos-grid">
            <?php foreach ($photos as $img): ?>
                <div class="photo-card"><a href="<?php echo $img; ?>" target="_blank"><img src="<?php echo $img; ?>"></a></div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>