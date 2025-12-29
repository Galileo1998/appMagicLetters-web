<?php
session_start();
require '../db_config.php';

if (!isset($_SESSION['tech_id'])) {
    header("Location: index.php");
    exit;
}

$tech_id = $_SESSION['tech_id'];
$tech_name = $_SESSION['tech_name'];

// Traemos las cartas pendientes
$sql = "SELECT * FROM letters WHERE tech_id = ? AND status != 'COMPLETADO' ORDER BY due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tech_id]);
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mis Cartas</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #eef2f5; margin: 0; padding-bottom: 80px; }
        
        /* Navbar */
        .navbar { background: #1e62d0; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .navbar h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .logout { color: white; text-decoration: none; font-size: 13px; border: 1px solid rgba(255,255,255,0.5); padding: 5px 12px; border-radius: 20px; }

        .container { padding: 20px 15px; max-width: 500px; margin: auto; }
        
        /* Tarjeta Réplica */
        .slip-replica { background: #fff; border-radius: 8px; margin-bottom: 25px; overflow: hidden; border: 1px solid #dce1e6; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .slip-replica::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: repeating-linear-gradient(45deg, #1e62d0, #1e62d0 10px, #fff 10px, #fff 20px); }

        .slip-header { display: flex; justify-content: space-between; padding: 20px 15px 10px; border-bottom: 2px dashed #eee; }
        .slip-brand { font-weight: 800; color: #1e62d0; letter-spacing: -0.5px; }
        .slip-id-box { background: #f0f2f5; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: bold; color: #555; font-size: 12px; }

        .slip-body { padding: 20px 15px; }
        .child-name { margin: 0 0 5px 0; color: #222; font-size: 1.3em; line-height: 1.2; }
        .child-nbr { color: #666; font-weight: 600; font-size: 0.9em; display: block; margin-bottom: 15px; }
        
        .details-grid { background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 0.9em; }
        .detail-row { display: flex; margin-bottom: 5px; }
        .detail-row strong { color: #888; width: 100px; font-size: 0.85em; text-transform: uppercase; }
        .detail-row span { color: #333; font-weight: 600; flex: 1; }

        .slip-footer { padding: 15px; text-align: center; background: #fff; border-top: 2px solid #f0f0f0; }
        
        /* Botón */
        .actions { padding: 0 15px 15px; }
        .btn-complete { background: #28a745; color: white; border: none; padding: 14px; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; font-size: 16px; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(40, 167, 69, 0.2); }
        .btn-complete:hover { background: #218838; }

        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
    </style>
</head>
<body>

    <div class="navbar">
        <div>
            <small style="opacity:0.8; font-size:11px; display:block;">Técnico</small>
            <h1><?= htmlspecialchars(explode(' ', $tech_name)[0]) ?></h1>
        </div>
        <a href="logout.php" class="logout">Salir</a>
    </div>

    <div class="container">
        <?php if(empty($cartas)): ?>
            <div class="empty-state">
                <h2>¡Todo entregado! 🎉</h2>
                <p>No tienes cartas pendientes.</p>
            </div>
        <?php else: ?>
            <?php foreach($cartas as $c): ?>
            
            <div class="slip-replica">
                <div class="slip-header">
                    <div class="slip-brand">MAIL SERVICE</div>
                    <div class="slip-id-box">ID: <?= htmlspecialchars($c['slip_id']) ?></div>
                </div>

                <div class="slip-body">
                    <h3 class="child-name"><?= htmlspecialchars($c['child_name']) ?></h3>
                    <span class="child-nbr">N° Niño: <?= htmlspecialchars($c['child_nbr']) ?></span>
                    
                    <div class="details-grid">
                        <div class="detail-row">
                            <strong>Comunidad:</strong>
                            <span><?= htmlspecialchars($c['village']) ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Patrocinador:</strong>
                            <span><?= htmlspecialchars($c['contact_name'] ?: 'N/D') ?></span>
                        </div>
                    </div>
                </div>

                <div class="slip-footer">
                    <svg class="barcode"
                         jsbarcode-value="<?= htmlspecialchars($c['slip_id']) ?>"
                         jsbarcode-format="CODE128"
                         jsbarcode-width="2"
                         jsbarcode-height="50"
                         jsbarcode-fontSize="14"
                         jsbarcode-displayValue="true">
                    </svg>
                    
                    <div style="margin-top:8px;">
                        <span style="background:#fff3cd; color:#856404; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:bold; border:1px solid #ffeeba;">
                            📅 Límite: <?= htmlspecialchars($c['due_date']) ?>
                        </span>
                    </div>
                </div>

                <div class="actions">
                    <form action="marcar_entregada.php" method="POST" onsubmit="return confirm('¿Confirmas la entrega?');">
                        <input type="hidden" name="letter_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn-complete">
                            ✓ Confirmar Entrega
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Inicialización simple: JsBarcode leerá automáticamente los atributos del HTML
        document.addEventListener("DOMContentLoaded", function() {
            JsBarcode(".barcode").init();
        });
    </script>

</body>
</html>