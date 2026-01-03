<?php
session_start();

// VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión de admin, mandar al login principal
    header("Location: ../index.php");
    exit;
}

require '../db_config.php';

// 1. Obtener Cartas (Misma lógica PHP)
$sql = "SELECT l.*, t.full_name as tech_name 
        FROM letters l 
        LEFT JOIN technicians t ON l.tech_id = t.id 
        WHERE l.status != 'COMPLETADO' 
        ORDER BY l.id DESC";
$stmt = $pdo->query($sql);
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Técnicos Activos para el Modal
$stmt_tech = $pdo->query("SELECT * FROM technicians WHERE status = 'ACTIVO'");
$tecnicos = $stmt_tech->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación - MagicLetter</title>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <style>
        /* --- PALETA DE COLORES --- */
        :root {
            --color-primary: #46B094;
            --color-support: #34859B;
            --color-accent: #B4D6E0;
            --color-bg: #f4f7f6;
        }

        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); padding: 20px; color: #444; }
        
        .card { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            border-top: 5px solid var(--color-primary); 
            margin: auto; max-width: 98%;
        }
        
        .header-area { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 15px; 
        }
        h1 { margin: 0; color: var(--color-primary); font-size: 24px; font-weight: 800; }
        
        /* Estilos Tabla DataTables */
        table.dataTable thead th {
            background-color: var(--color-primary);
            color: white;
            padding: 12px;
            font-weight: 600;
            border-bottom: none;
        }
        
        /* Inputs de búsqueda en el pie */
        tfoot input {
            width: 100%; padding: 6px; box-sizing: border-box;
            border: 1px solid #ddd; border-radius: 4px; font-size: 12px;
        }

        /* Badges */
        .badge-tech { padding: 5px 10px; border-radius: 20px; font-weight: bold; font-size: 11px; display: inline-block; }
        .badge-unassigned { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; } 
        .badge-assigned { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        /* Botón Asignar */
        .btn-assign { 
            background: var(--color-support); color: white; border: none; 
            padding: 6px 14px; border-radius: 4px; cursor: pointer; 
            font-weight: 600; font-size: 12px; transition: 0.2s; 
        }
        .btn-assign:hover { background: #2a6e80; transform: translateY(-1px); }

        /* --- MODAL --- */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(52, 133, 155, 0.5); backdrop-filter: blur(2px); }
        .modal-content { 
            background-color: white; margin: 10% auto; padding: 30px; 
            border-radius: 12px; width: 400px; text-align: center; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); position: relative;
            animation: slideDown 0.3s ease;
        }
        select { 
            width: 100%; padding: 12px; margin: 20px 0; border: 1px solid #B4D6E0; 
            border-radius: 6px; font-size: 14px; outline: none;
        }
        select:focus { border-color: var(--color-primary); }
        
        .btn-save { 
            background: var(--color-primary); color: white; border: none; 
            padding: 12px 20px; border-radius: 6px; cursor: pointer; 
            width: 100%; font-size: 15px; font-weight: bold; transition: background 0.3s;
        }
        .btn-save:hover { background: var(--color-support); }
        
        .close { 
            position: absolute; right: 15px; top: 10px; font-size: 24px; 
            cursor: pointer; color: #aaa; transition: color 0.2s; 
        }
        .close:hover { color: var(--color-primary); }

        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="card">
    <div class="header-area">
        <div>
            <h1>📋 Asignación de Cartas</h1>
            <small style="color:#888;">Filtra, asigna y exporta la carga de trabajo.</small>
        </div>
    </div>

    <table id="tablaAsignacion" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Slip ID</th>
                <th>N° Niño</th>
                <th>Nombre</th>
                <th>Comunidad</th>
                <th>Técnico</th>
                <th>Acción</th>
                <th>Fecha Límite</th>
                <th>Patrocinador</th>
                <th>ID Patr.</th>
                <th>Sexo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($cartas as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['slip_id']) ?></strong></td>
                <td><?= htmlspecialchars($c['child_code']) ?></td>
                <td><?= htmlspecialchars($c['child_name']) ?></td>
                <td><?= htmlspecialchars($c['village']) ?></td>
                
                <td>
                    <?php if($c['tech_name']): ?>
                        <span class="badge-tech badge-assigned">👤 <?= htmlspecialchars($c['tech_name']) ?></span>
                        <div style="font-size:10px; color:#888; margin-top:2px;">
                            <?= !empty($c['assigned_at']) ? date('d/m/Y', strtotime($c['assigned_at'])) : '' ?>
                        </div>
                    <?php else: ?>
                        <span class="badge-tech badge-unassigned">⚠️ Pendiente</span>
                    <?php endif; ?>
                </td>
                
                <td>
                    <button onclick="abrirModal(<?= $c['id'] ?>)" class="btn-assign">
                        <?= $c['tech_name'] ? '🔄 Reasignar' : '➕ Asignar' ?>
                    </button>
                </td>

                <td><?= htmlspecialchars($c['due_date']) ?></td>
                <td><?= htmlspecialchars($c['contact_name']) ?></td>
                <td><?= htmlspecialchars($c['contact_id']) ?></td>
                <td><?= htmlspecialchars($c['sex']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>Slip ID</th>
                <th>N° Niño</th>
                <th>Nombre</th>
                <th>Comunidad</th>
                <th>Técnico</th>
                <th></th> <th>Fecha</th>
                <th>Patrocinador</th>
                <th>ID Patr.</th>
                <th>Sexo</th>
            </tr>
        </tfoot>
    </table>
</div>

<div id="modalAsignar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2 style="color:var(--color-primary); margin-top:0;">Asignar Técnico</h2>
        <p style="color:#666; font-size:14px;">Selecciona quién será responsable de esta carta.</p>

        <form action="procesar_asignacion.php" method="POST">
            <input type="hidden" name="letter_id" id="letter_id_input">
            
            <select name="tech_id" required>
                <option value="">-- Seleccionar Técnico --</option>
                <?php foreach($tecnicos as $t): ?>
                    <option value="<?= $t['id'] ?>">
                        <?= htmlspecialchars($t['full_name']) ?> 
                        (<?= $t['community_assigned'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-save">Confirmar Asignación</button>
        </form>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script> <script>
    $(document).ready(function() {
        // 1. Configurar inputs de búsqueda en el pie
        $('#tablaAsignacion tfoot th').each(function() {
            var title = $(this).text();
            if(title !== '') {
                $(this).html('<input type="text" placeholder="🔍 '+title+'" />');
            }
        });

        // 2. Inicializar DataTable
        var table = $('#tablaAsignacion').DataTable({
            dom: 'Bfrtip',
            buttons: [
                { extend: 'colvis', text: '👁️ Ver Columnas', className: 'btn-dt' }, // Botón para mostrar/ocultar columnas
                { extend: 'excelHtml5', text: '📊 Excel', className: 'btn-dt' },
                { extend: 'csvHtml5', text: '📄 CSV', className: 'btn-dt' },
                { extend: 'print', text: '🖨️ Imprimir', className: 'btn-dt' }
            ],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            order: [[ 0, "desc" ]],
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

        // Estilizar botones de la tabla
        $('.dt-button').css({
            'background': '#34859B', 'color': 'white', 'border': 'none', 
            'border-radius': '5px', 'margin-right': '5px', 'font-size':'13px'
        });
    });

    // Lógica del Modal (Mantenida)
    var modal = document.getElementById("modalAsignar");
    var inputId = document.getElementById("letter_id_input");

    function abrirModal(idCarta) {
        inputId.value = idCarta;
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