<?php

session_start();

// VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión de admin, mandar al login principal
    header("Location: ../index.php");
    exit;
}

require '../db_config.php';


// Obtener datos (Mantenemos tu lógica PHP intacta)
$sql = "SELECT l.*, t.full_name as tech_name 
        FROM letters l 
        LEFT JOIN technicians t ON l.tech_id = t.id 
        ORDER BY l.id DESC";
$stmt = $pdo->query($sql);
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores
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
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <style>
        /* --- NUEVA PALETA DE COLORES --- */
        :root {
            --color-primary: #46B094;  /* Identidad */
            --color-support: #34859B;  /* Apoyo */
            --color-accent: #B4D6E0;   /* Acento */
            --color-bg: #f4f7f6;
        }

        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); padding: 20px; color: #444; }
        .container { max-width: 1200px; margin: auto; }
        
        /* Dashboard Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { 
            background: white; padding: 25px; border-radius: 12px; text-align: center; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom: 4px solid var(--color-accent);
        }
        .stat-number { font-size: 2.5em; font-weight: 800; color: var(--color-support); margin-bottom: 5px; }
        .stat-label { font-weight: 600; color: #888; text-transform: uppercase; font-size: 0.85em; letter-spacing: 1px; }
        
        /* Colores específicos para KPIs */
        .stat-card.pending { border-color: #ffc107; }
        .stat-card.done { border-color: var(--color-primary); }

        /* Panel Principal */
        .main-card { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            border-top: 5px solid var(--color-primary); /* Toque de identidad */
        }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        h3 { color: var(--color-primary); margin: 0; font-size: 1.5em; }

        /* Botones del Menú */
        .menu-btn { text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; font-size: 13px; display: inline-block; transition: all 0.3s; margin-left: 5px; }
        .btn-blue { background: var(--color-primary); color: white; }
        .btn-blue:hover { background: var(--color-support); }
        .btn-green { background: var(--color-support); color: white; }
        .btn-green:hover { background: #2a6e80; }
        .btn-outline { border: 2px solid var(--color-primary); color: var(--color-primary); background: white; }
        .btn-outline:hover { background: var(--color-primary); color: white; }

        /* DataTables Personalización */
        table.dataTable thead th {
            background-color: var(--color-accent);
            color: var(--color-support);
            font-weight: 700;
            border-bottom: 2px solid white;
        }
        table.dataTable tbody tr:hover { background-color: #f1fcf9; }
        
        /* Inputs de búsqueda en el pie de tabla */
        tfoot input {
            width: 100%; padding: 6px; box-sizing: border-box;
            border: 1px solid #ddd; border-radius: 4px; font-size: 12px;
        }

        /* Estados */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-done { background: #d4edda; color: #155724; }
        .badge-return { background: #f8d7da; color: #721c24; }

        /* Botones de Acción en Tabla */
        .btn-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: transform 0.2s; text-decoration: none; font-size: 14px; }
        .btn-icon:hover { transform: scale(1.15); }
        .btn-ticket { background: var(--color-accent); color: var(--color-support); }
        .btn-eye { background: var(--color-primary); color: white; }

        /* Estilos del Modal (Mantenidos y pulidos) */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(52, 133, 155, 0.6); backdrop-filter: blur(3px); }
        .modal-content { background-color: transparent; margin: 5% auto; width: 400px; position: relative; animation: slideDown 0.4s ease; }
        .close-modal { position: absolute; right: -40px; top: 0; color: white; font-size: 30px; cursor: pointer; font-weight: bold; }
        
        /* Boleta */
        .slip-replica { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.25); }
        .slip-header { display: flex; justify-content: space-between; padding: 20px; border-bottom: 2px dashed #eee; background: #fff; }
        .slip-brand { font-weight: 800; color: var(--color-primary); letter-spacing: -0.5px; }
        .child-name { color: var(--color-support); }
        
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <h1 style="color:var(--color-primary); margin:0; font-weight:800;">Panel de Administración</h1>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $total ?></div>
            <div class="stat-label">Total Cartas</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-number" style="color:#f0ad4e;"><?= $pendientes ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
        <div class="stat-card done">
            <div class="stat-number" style="color:#46B094;"><?= $completadas ?></div>
            <div class="stat-label">Completadas</div>
        </div>
    </div>

    <div class="main-card">
        <div class="header">
            <h3>📜 Listado Maestro</h3>
            <div>
                <a href="tecnicos.php" class="menu-btn btn-outline">👷 Técnicos</a>
                <a href="asignaciones.php" class="menu-btn btn-blue">📋 Asignar</a>
                <a href="revisar_carga.php" class="menu-btn btn-green">🚀 Cargar PDF</a>
            </div>
        </div>

        <table id="tablaCartas" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Slip ID</th>
                    <th>Niño</th>
                    <th>Comunidad</th>
                    <th>Técnico</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cartas as $c): ?>
                <tr>
                    <td><strong>#<?= htmlspecialchars($c['slip_id']) ?></strong></td>
                    <td><?= htmlspecialchars($c['child_name']) ?></td>
                    <td><?= htmlspecialchars($c['village']) ?></td>
                    <td style="color:#666;">
                        <?= $c['tech_name'] ? '👤 '.$c['tech_name'] : '<span style="color:#aaa; font-style:italic;">--</span>' ?>
                    </td>
                    <td>
                        <?php 
                            $statusClass = 'badge-pending';
                            if($c['status'] == 'COMPLETADO' || $c['status'] == 'SYNCED') $statusClass = 'badge-done';
                            if($c['status'] == 'RETURNED') $statusClass = 'badge-return';
                        ?>
                        <span class="badge <?= $statusClass ?>">
                            <?= $c['status'] ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-icon btn-ticket" onclick='verTarjeta(<?= json_encode($c) ?>)' title="Ver Ticket">
                                🎫
                            </button>

                            <?php if(in_array($c['status'], ['COMPLETADO', 'SYNCED', 'EN_REVISION', 'RETURNED'])): ?>
                                <a href="ver_carta.php?id=<?= $c['id'] ?>" class="btn-icon btn-eye" title="Revisar Carta">
                                    👁️
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Slip ID</th>
                    <th>Niño</th>
                    <th>Comunidad</th>
                    <th>Técnico</th>
                    <th>Estado</th>
                    <th></th> </tr>
            </tfoot>
        </table>
    </div>
</div>

<div id="modalTarjeta" class="modal">
    <span class="close-modal" onclick="cerrarModal()">&times;</span>
    <div class="modal-content">
        <div class="slip-replica">
            <div class="slip-header">
                <div class="slip-brand">MAGIC LETTER</div>
                <div style="background:#f4f7f6; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:12px; color:#555;">ID: <span id="m_slip_display"></span></div>
            </div>
            <div style="padding:20px;">
                <h3 class="child-name" id="m_child_name" style="margin:0 0 5px 0;"></h3>
                <span id="m_child_nbr" style="color:#888; font-size:0.9em; display:block; margin-bottom:15px;"></span>
                
                <div style="background:#f8f9fa; padding:12px; border-radius:6px; font-size:0.9em;">
                    <div style="display:flex; margin-bottom:5px;"><strong style="width:100px; color:#888;">Comunidad:</strong> <span id="m_village"></span></div>
                    <div style="display:flex; margin-bottom:5px;"><strong style="width:100px; color:#888;">Patrocinador:</strong> <span id="m_contact"></span></div>
                    <div style="display:flex;"><strong style="width:100px; color:#888;">Técnico:</strong> <span id="m_tech" style="color:var(--color-primary); font-weight:bold;"></span></div>
                </div>
            </div>
            <div style="padding:15px; text-align:center; border-top:1px solid #eee;">
                <svg id="barcode"></svg>
                <div style="margin-top:10px;">
                    <span style="background:#fff3cd; color:#856404; padding:4px 12px; border-radius:12px; font-size:11px; font-weight:bold;">
                        📅 Límite: <span id="m_date"></span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
    // 1. INICIALIZAR DATATABLE
    $(document).ready(function() {
        // Configuración de los inputs de búsqueda
        $('#tablaCartas tfoot th').each(function() {
            var title = $(this).text();
            if(title !== '') {
                $(this).html('<input type="text" placeholder="🔍 '+title+'" />');
            }
        });

        // Crear la tabla
        var table = $('#tablaCartas').DataTable({
            dom: 'Bfrtip',
            buttons: [
                { extend: 'excelHtml5', className: 'btn-export', text: '📊 Excel', titleAttr: 'Exportar a Excel' },
                { extend: 'csvHtml5', className: 'btn-export', text: '📄 CSV' },
                { extend: 'print', className: 'btn-export', text: '🖨️ Imprimir' }
            ],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            order: [[ 0, "desc" ]], // Ordenar por Slip ID descendente
            initComplete: function () {
                // Lógica de búsqueda por columna
                this.api().columns().every(function () {
                    var that = this;
                    $('input', this.footer()).on('keyup change clear', function () {
                        if (that.search() !== this.value) {
                            that.search(this.value).draw();
                        }
                    });
                });
            }
        });

        // Estilo manual a los botones de DataTables para que coincidan con tu tema
        $('.dt-button').css({
            'background': '#34859B', 'color': 'white', 'border': 'none', 
            'border-radius': '5px', 'padding':'6px 12px', 'margin-right':'5px', 'font-size':'13px'
        });
    });

    // 2. LÓGICA DEL MODAL (Tu código original mejorado)
    var modal = document.getElementById("modalTarjeta");

    function verTarjeta(data) {
        document.getElementById('m_slip_display').innerText = data.slip_id;
        document.getElementById('m_child_name').innerText = data.child_name;
        document.getElementById('m_child_nbr').innerText = "N° " + data.child_code;
        document.getElementById('m_village').innerText = data.village;
        document.getElementById('m_contact').innerText = data.contact_name ? data.contact_name : "N/D";
        document.getElementById('m_date').innerText = data.due_date;
        document.getElementById('m_tech').innerText = data.tech_name ? data.tech_name : "Sin asignar";

        JsBarcode("#barcode", data.slip_id, {
            format: "CODE128", lineColor: "#333", width: 2, height: 40, displayValue: true, fontSize: 14
        });

        modal.style.display = "block";
    }

    function cerrarModal() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) cerrarModal();
    }
</script>

</body>
</html>