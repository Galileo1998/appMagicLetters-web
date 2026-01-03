<?php
session_start();
require '../db_config.php';

if (!isset($_SESSION['tech_id'])) {
    header("Location: index.php");
    exit;
}

$tech_id = $_SESSION['tech_id'];
$tech_name = $_SESSION['tech_name'];

// Cartas pendientes para el técnico
$sql = "SELECT * FROM letters WHERE tech_id = ? AND status IN ('PENDIENTE', 'ASSIGNED', 'RETURNED') ORDER BY due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tech_id]);
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mis Cartas - MagicLetter</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- NUEVA PALETA --- */
        :root {
            --color-primary: #46B094;
            --color-support: #34859B;
            --color-bg: #f4f7f6;
            --color-card-bg: #ffffff;
        }

        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); margin: 0; padding-bottom: 80px; }
        
        /* Navbar */
        .navbar { 
            background: var(--color-primary); 
            color: white; padding: 15px 20px; 
            display: flex; justify-content: space-between; align-items: center; 
            position: sticky; top: 0; z-index: 100; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
        }
        .navbar h1 { margin: 0; font-size: 18px; font-weight: 700; }
        
        .logout { 
            color: white; text-decoration: none; font-size: 13px; 
            border: 1px solid rgba(255,255,255,0.4); padding: 6px 14px; 
            border-radius: 20px; transition: background 0.2s;
        }
        .logout:active { background: rgba(255,255,255,0.2); }

        .container { padding: 20px 15px; max-width: 500px; margin: auto; }
        
        /* Tarjeta Estilo Boleta */
        .slip-replica { 
            background: var(--color-card-bg); 
            border-radius: 12px; margin-bottom: 25px; overflow: hidden; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.08); 
            border: 1px solid #eee; position: relative;
        }
        
        /* Borde superior decorativo */
        .slip-replica::before { 
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; 
            background: repeating-linear-gradient(45deg, var(--color-primary), var(--color-primary) 10px, #fff 10px, #fff 20px); 
        }

        /* Cabecera de la Boleta */
        .slip-header { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 20px 20px 15px; border-bottom: 2px dashed #f0f0f0; 
        }
        .slip-brand { font-weight: 800; color: var(--color-support); font-size: 14px; letter-spacing: 0.5px; }
        .slip-id-box { 
            background: #eefcf8; color: var(--color-primary); 
            padding: 5px 10px; border-radius: 6px; 
            font-family: monospace; font-weight: bold; font-size: 14px; 
        }

        /* Cuerpo */
        .slip-body { padding: 20px; }
        .child-name { margin: 0 0 5px 0; color: #333; font-size: 1.4em; font-weight: 700; line-height: 1.2; }
        .child-nbr { color: #888; font-weight: 600; font-size: 0.95em; display: block; margin-bottom: 20px; }
        
        /* Grilla de detalles */
        .details-grid { 
            background: #f8f9fa; padding: 15px; border-radius: 8px; font-size: 0.95em; 
            border-left: 4px solid var(--color-support);
        }
        .detail-row { display: flex; margin-bottom: 8px; }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-row strong { color: #666; width: 110px; font-size: 0.9em; text-transform: uppercase; }
        .detail-row span { color: #333; font-weight: 600; flex: 1; }

        /* Pie de Boleta */
        .slip-footer { padding: 15px; text-align: center; background: #fff; }
        
        .date-badge {
            background: #fff8e1; color: #b7791f; 
            padding: 5px 15px; border-radius: 20px; 
            font-size: 12px; font-weight: bold; 
            display: inline-block; margin-top: 10px;
            border: 1px solid #ffeeba;
        }

        /* Botón de Acción */
        .actions { padding: 0 20px 20px; }
        
        .btn-sync {
            background: #f0f2f5; color: #555; text-align: center; 
            padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 500;
            display: block; margin-bottom: 10px;
        }

        .empty-state { text-align: center; padding: 80px 20px; color: #999; }
        .empty-icon { font-size: 60px; color: #ddd; margin-bottom: 15px; display: block; }
        
        .badge-returned {
            background: #ffe6e6; color: #dc3545; padding: 5px 10px; 
            border-radius: 4px; font-size: 12px; font-weight: bold; 
            margin-bottom: 15px; display: inline-block;
        }
    </style>
</head>
<body>

    <div class="navbar">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="background:rgba(255,255,255,0.2); width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                <i class="fa-solid fa-user"></i>
            </div>
            <div>
                <small style="opacity:0.8; font-size:11px; display:block; letter-spacing:0.5px;">TÉCNICO</small>
                <h1><?= htmlspecialchars(explode(' ', $tech_name)[0]) ?></h1>
            </div>
        </div>
        <a href="logout.php" class="logout"><i class="fa-solid fa-power-off"></i> Salir</a>
    </div>

    <div class="container">
        
        <div style="text-align:center; margin-bottom:20px; color:#666; font-size:13px;">
            <i class="fa-solid fa-cloud-arrow-down"></i> Sincroniza desde la App Móvil para actualizar el estado.
        </div>

        <?php if(empty($cartas)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-check-circle empty-icon"></i>
                <h2 style="color:#333; margin:0;">¡Todo listo!</h2>
                <p>No tienes cartas pendientes de entrega.</p>
            </div>
        <?php else: ?>
            <?php foreach($cartas as $c): ?>
            
            <div class="slip-replica">
                <div class="slip-header">
                    <div class="slip-brand">MAGIC LETTER</div>
                    <div class="slip-id-box">#<?= htmlspecialchars($c['slip_id']) ?></div>
                </div>

                <div class="slip-body">
                    
                    <?php if($c['status'] == 'RETURNED'): ?>
                        <div class="badge-returned">
                            <i class="fa-solid fa-triangle-exclamation"></i> DEVUELTA - REVISAR EN APP
                        </div>
                    <?php endif; ?>

                    <h3 class="child-name"><?= htmlspecialchars($c['child_name']) ?></h3>
                    <span class="child-nbr">ID Niño: <?= htmlspecialchars($c['child_nbr'] ?: $c['child_code']) ?></span>
                    
                    <div class="details-grid">
                        <div class="detail-row">
                            <strong>Comunidad:</strong>
                            <span><?= htmlspecialchars($c['village']) ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Patrocinador:</strong>
                            <span><?= htmlspecialchars($c['contact_name'] ?: 'No disponible') ?></span>
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
                    
                    <div>
                        <span class="date-badge">
                            <i class="fa-regular fa-calendar"></i> Límite: <?= htmlspecialchars($c['due_date']) ?>
                        </span>
                    </div>
                    <br>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Generar códigos de barras automáticamente
            JsBarcode(".barcode").init();
        });
    </script>

</body>
</html>