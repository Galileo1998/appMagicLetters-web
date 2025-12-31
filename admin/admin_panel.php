<?php
session_start();
require '../db_config.php';

// Obtener todas las cartas con el nombre del técnico asignado (si lo hay)
$sql = "SELECT l.*, t.full_name as tech_name 
        FROM letters l 
        LEFT JOIN technicians t ON l.tech_id = t.id 
        ORDER BY l.id DESC";
$stmt = $pdo->query($sql);
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores para el Dashboard
$total = count($cartas);
$pendientes = count(array_filter($cartas, fn($c) => $c['status'] == 'PENDIENTE'));
$completadas = count(array_filter($cartas, fn($c) => $c['status'] == 'COMPLETADO'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 1200px; margin: auto; }
        
        /* Dashboard Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-number { font-size: 2em; font-weight: bold; color: #1e62d0; }
        
        /* Panel Principal */
        .main-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        /* Botones del Menú */
        .menu-btn { text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; margin-left: 10px; font-size: 14px; display: inline-block; }
        .btn-blue { background: #1e62d0; color: white; }
        .btn-green { background: #28a745; color: white; }
        .btn-outline { border: 1px solid #1e62d0; color: #1e62d0; }
        .btn-outline:hover { background: #e7f3ff; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8f9fa; padding: 12px; text-align: left; color: #555; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        /* Estados */
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-done { background: #d4edda; color: #155724; }

        /* Botón Ver Tarjeta */
        .btn-view { background: #17a2b8; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; }
        .btn-view:hover { transform: scale(1.1); background: #138496; }

        /* ================= MODAL DE TARJETA ================= */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(2px); }
        .modal-content { background-color: transparent; margin: 5% auto; width: 400px; position: relative; animation: slideDown 0.3s ease; }
        .close-modal { position: absolute; right: -40px; top: 0; color: white; font-size: 30px; cursor: pointer; font-weight: bold; }

        /* Diseño de la Boleta (Copia exacta del Técnico) */
        .slip-replica { background: #fff; border-radius: 8px; overflow: hidden; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
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

        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="color:#1e62d0; margin:0;">Panel de Administración</h1>
        <a href="../tecnico/index.php" style="color:#666; text-decoration:none; font-size:14px;">Ir al Login Técnico →</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $total ?></div>
            <div>Total Cartas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#f0ad4e;"><?= $pendientes ?></div>
            <div>Pendientes</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#28a745;"><?= $completadas ?></div>
            <div>Completadas</div>
        </div>
    </div>

    <div class="main-card">
        <div class="header">
            <h3>📜 Listado Maestro</h3>
            <div>
                <a href="tecnicos.php" class="menu-btn btn-outline">👷 Gestionar Técnicos</a>
                <a href="asignaciones.php" class="menu-btn btn-blue">📋 Asignar Cartas</a>
                <a href="revisar_carga.php" class="menu-btn btn-green">🚀 Cargar PDF</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Slip ID</th>
                    <th>Niño</th>
                    <th>Comunidad</th>
                    <th>Técnico</th>
                    <th>Estado</th>
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cartas as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['slip_id']) ?></strong></td>
                    <td><?= htmlspecialchars($c['child_name']) ?></td>
                    <td><?= htmlspecialchars($c['village']) ?></td>
                    <td style="color:#666;">
                        <?= $c['tech_name'] ? '👤 '.$c['tech_name'] : '<span style="color:#aaa;">--</span>' ?>
                    </td>
                    <td>
                        <span class="badge <?= ($c['status'] == 'COMPLETADO' || $c['status'] == 'SYNCED') ? 'badge-done' : 'badge-pending' ?>">
                            <?= $c['status'] ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:5px;">
                            <button class="btn-view" onclick='verTarjeta(<?= json_encode($c) ?>)' title="Ver Slip / Código de Barras">
                                🎫
                            </button>

                            <?php if($c['status'] == 'COMPLETADO' || $c['status'] == 'SYNCED' || $c['status'] == 'EN_REVISION'): ?>
                                <a href="ver_carta.php?id=<?= $c['id'] ?>" class="btn-view" style="background:#28a745; text-decoration:none;" title="Ver Fotos y Mensaje">
                                    👁️
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalTarjeta" class="modal">
    <span class="close-modal" onclick="cerrarModal()">&times;</span>
    
    <div class="modal-content">
        <div class="slip-replica">
            <div class="slip-header">
                <div class="slip-brand">MAIL SERVICE</div>
                <div class="slip-id-box" id="m_slip_display">ID: 000000</div>
            </div>

            <div class="slip-body">
                <h3 class="child-name" id="m_child_name">Nombre del Niño</h3>
                <span class="child-nbr" id="m_child_nbr">N° Niño: 000000</span>
                
                <div class="details-grid">
                    <div class="detail-row">
                        <strong>Comunidad:</strong>
                        <span id="m_village">---</span>
                    </div>
                    <div class="detail-row">
                        <strong>Patrocinador:</strong>
                        <span id="m_contact">---</span>
                    </div>
                    <div class="detail-row">
                        <strong>Técnico:</strong>
                        <span id="m_tech" style="color:#1e62d0;">---</span>
                    </div>
                </div>
            </div>

            <div class="slip-footer">
                <svg id="barcode"></svg>
                
                <div style="margin-top:8px;">
                    <span style="background:#fff3cd; color:#856404; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:bold; border:1px solid #ffeeba;">
                        📅 Límite: <span id="m_date">---</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var modal = document.getElementById("modalTarjeta");

    function verTarjeta(data) {
        // 1. Llenar los datos visuales
        document.getElementById('m_slip_display').innerText = "ID: " + data.slip_id;
        document.getElementById('m_child_name').innerText = data.child_name;
        document.getElementById('m_child_nbr').innerText = "N° Niño: " + data.child_code;
        document.getElementById('m_village').innerText = data.village;
        document.getElementById('m_contact').innerText = data.contact_name ? data.contact_name : "N/D";
        document.getElementById('m_date').innerText = data.due_date;
        document.getElementById('m_tech').innerText = data.tech_name ? data.tech_name : "Sin asignar";

        // 2. Generar el Código de Barras al vuelo
        JsBarcode("#barcode", data.slip_id, {
            format: "CODE128",
            lineColor: "#333",
            width: 2,
            height: 50,
            displayValue: true,
            fontSize: 14
        });

        // 3. Mostrar el modal
        modal.style.display = "block";
    }

    function cerrarModal() {
        modal.style.display = "none";
    }

    // Cerrar al hacer clic fuera
    window.onclick = function(event) {
        if (event.target == modal) { cerrarModal(); }
    }
</script>

</body>
</html>