<?php
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

$photos = []; $drawing = null;
foreach ($attachments as $att) {
    $path = "../" . $att['file_path'];
    if ($att['file_type'] === 'DRAWING') $drawing = $path;
    else $photos[] = $path;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión Carta #<?php echo $letter['slip_id']; ?></title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .card { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .status { padding: 5px 15px; border-radius: 20px; color: white; font-weight: bold; float: right; }
        .msg { background: #fffbe6; padding: 20px; border-radius: 8px; border: 1px solid #ffe58f; white-space: pre-wrap; margin: 20px 0; }
        .img-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .img-grid img { width: 100%; border-radius: 8px; border: 1px solid #ddd; }
        .btn { padding: 10px 20px; border-radius: 5px; cursor: pointer; border: none; font-weight: bold; }
        .btn-ret { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="card">
        <span class="status" style="background: <?php echo $letter['status']=='RETURNED' ? '#dc3545' : '#28a745'; ?>"><?php echo $letter['status']; ?></span>
        <h1><?php echo htmlspecialchars($letter['child_name']); ?></h1>
        <p><b>Comunidad:</b> <?php echo $letter['village']; ?> | <b>ID:</b> #<?php echo $letter['slip_id']; ?></p>
        
        <h3>Mensaje:</h3>
        <div class="msg"><?php echo nl2br(htmlspecialchars($letter['final_message'])); ?></div>

        <h3>Dibujo:</h3>
        <?php if($drawing): ?><img src="<?php echo $drawing; ?>" style="max-width:100%; border:1px solid #ddd;"><?php endif; ?>

        <h3>Fotos:</h3>
        <div class="img-grid">
            <?php foreach($photos as $p): ?><img src="<?php echo $p; ?>"><?php endforeach; ?>
        </div>

        <div style="margin-top:30px; text-align:right;">
            <button onclick="ret()" class="btn btn-ret">🚫 Devolver al Técnico</button>
            <button onclick="window.print()" class="btn">Imprimir</button>
        </div>
    </div>
    <script>
    function ret() {
        let r = prompt("Motivo de devolución:");
        if (r) {
            fetch('../api/return_letter.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: <?php echo $letter['id']; ?>, reason: r })
            }).then(() => window.location.reload());
        }
    }
    </script>
</body>
</html>