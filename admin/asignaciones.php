<?php
session_start();
require '../db_config.php';

// 1. Obtener Cartas con el nombre del Técnico
// Traemos TODOS los campos de letters (l.*) y el nombre del técnico
$sql = "SELECT l.*, t.full_name as tech_name 
        FROM letters l 
        LEFT JOIN technicians t ON l.tech_id = t.id 
        WHERE l.status != 'COMPLETADO' 
        ORDER BY l.id DESC";
$stmt = $pdo->query($sql);
$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Obtener lista de Técnicos Activos
$stmt_tech = $pdo->query("SELECT * FROM technicians WHERE status = 'ACTIVO'");
$tecnicos = $stmt_tech->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación de Cartas</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 98%; margin: auto; }
        
        /* Encabezado */
        .header-area { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; }
        h1 { margin: 0; color: #1e62d0; font-size: 24px; }
        
        /* Control de Columnas (Menú) */
        .column-control { position: relative; }
        .dropdown-btn { background: #6c757d; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .dropdown-btn:hover { background: #5a6268; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: #fff; min-width: 220px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 100; padding: 15px; border-radius: 6px; border: 1px solid #ddd; }
        .dropdown-content label { display: flex; align-items: center; padding: 5px 0; cursor: pointer; font-size: 14px; }
        .dropdown-content label:hover { color: #1e62d0; }
        .show { display: block; }

        /* Tabla */
        .table-wrapper { overflow-x: auto; max-height: 75vh; }
        table { width: 100%; border-collapse: collapse; min-width: 1400px; font-size: 13px; }
        th { background: #1e62d0; color: white; padding: 12px 10px; text-align: left; white-space: nowrap; position: sticky; top: 0; z-index: 10; }
        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background-color: #f8f9fa; }
        
        /* Estados y Badges */
        .badge-tech { padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 12px; display: inline-block; }
        .badge-unassigned { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; } 
        .badge-assigned { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        /* Botón Asignar */
        .btn-assign { background: #17a2b8; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px; transition: 0.2s; }
        .btn-assign:hover { background: #138496; transform: scale(1.05); }

        /* Clases para ocultar columnas */
        .hidden-col { display: none; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 200; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 10% auto; padding: 30px; border-radius: 12px; width: 400px; text-align: center; box-shadow: 0 5px 25px rgba(0,0,0,0.3); }
        select { width: 100%; padding: 12px; margin: 20px 0; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; }
        .btn-save:hover { background: #218838; }
        .close { float: right; font-size: 24px; cursor: pointer; color: #aaa; }
        .close:hover { color: #000; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="card">
    <div class="header-area">
        <div>
            <h1>📋 Asignación de Cartas</h1>
            <small style="color:#666;">Gestiona la entrega a técnicos de campo</small>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <div class="column-control">
                <button onclick="toggleDropdown()" class="dropdown-btn">👁️ Mostrar/Ocultar Campos</button>
                <div id="colDropdown" class="dropdown-content">
                    <strong style="display:block; margin-bottom:10px; color:#333;">Columnas Visibles:</strong>
                    
                    <label><input type="checkbox" checked onchange="toggleCol('col-slip')"> Slip ID</label>
                    <label><input type="checkbox" checked onchange="toggleCol('col-name')"> Nombre Niño</label>
                    <label><input type="checkbox" checked onchange="toggleCol('col-village')"> Comunidad</label>
                    <label><input type="checkbox" checked onchange="toggleCol('col-tech')"> Técnico</label>
                    <label><input type="checkbox" checked onchange="toggleCol('col-action')"> Acciones</label>
                    
                    <div style="border-top:1px solid #eee; margin:5px 0;"></div>
                    
                    <label><input type="checkbox" onchange="toggleCol('col-nbr')"> N° Niño</label>
                    <label><input type="checkbox" onchange="toggleCol('col-date')"> Fecha Límite</label>
                    <label><input type="checkbox" onchange="toggleCol('col-cname')"> Patrocinador</label>
                    <label><input type="checkbox" onchange="toggleCol('col-cid')"> ID Patrocinador</label>
                    <label><input type="checkbox" onchange="toggleCol('col-ia')"> IA ID</label>
                    <label><input type="checkbox" onchange="toggleCol('col-sex')"> Sexo</label>
                    <label><input type="checkbox" onchange="toggleCol('col-birth')"> Nacimiento</label>
                </div>
            </div>
            <a href="admin_panel.php" class="dropdown-btn" style="background:#e2e6ea; color:#333; text-decoration:none;">← Volver</a>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th class="col-slip">Slip ID</th>
                    <th class="col-nbr hidden-col">N° Niño</th>
                    <th class="col-name">Nombre del Niño</th>
                    <th class="col-village">Comunidad</th>
                    <th class="col-tech">Técnico Asignado</th>
                    <th class="col-action">Acción</th>

                    <th class="col-date hidden-col">Fecha Límite</th>
                    <th class="col-cname hidden-col">Patrocinador</th>
                    <th class="col-cid hidden-col">Contact ID</th>
                    <th class="col-ia hidden-col">IA ID</th>
                    <th class="col-sex hidden-col">Sexo</th>
                    <th class="col-birth hidden-col">Nacimiento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cartas as $c): ?>
                <tr>
                    <td class="col-slip"><strong><?= htmlspecialchars($c['slip_id']) ?></strong></td>
                    <td class="col-nbr hidden-col"><?= htmlspecialchars($c['child_nbr']) ?></td>
                    <td class="col-name"><?= htmlspecialchars($c['child_name']) ?></td>
                    <td class="col-village"><?= htmlspecialchars($c['village']) ?></td>
                    
                    <td class="col-tech">
                        <?php if($c['tech_name']): ?>
                            <span class="badge-tech badge-assigned">👤 <?= htmlspecialchars($c['tech_name']) ?></span>
                            <?php if(!empty($c['assigned_at'])): ?>
                                <div style="font-size:10px; color:#666; margin-top:2px;">
                                    <?= date('d/m/Y', strtotime($c['assigned_at'])) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge-tech badge-unassigned">⚠️ Pendiente</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="col-action">
                        <button onclick="abrirModal(<?= $c['id'] ?>)" class="btn-assign">
                            <?= $c['tech_name'] ? 'Reasignar' : 'Asignar' ?>
                        </button>
                    </td>

                    <td class="col-date hidden-col"><?= htmlspecialchars($c['due_date']) ?></td>
                    <td class="col-cname hidden-col"><?= htmlspecialchars($c['contact_name']) ?></td>
                    <td class="col-cid hidden-col"><?= htmlspecialchars($c['contact_id']) ?></td>
                    <td class="col-ia hidden-col"><?= htmlspecialchars($c['ia_id']) ?></td>
                    <td class="col-sex hidden-col"><?= htmlspecialchars($c['sex']) ?></td>
                    <td class="col-birth hidden-col"><?= htmlspecialchars($c['birthdate']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalAsignar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2 style="color:#1e62d0;">Asignar Técnico</h2>
        <p style="color:#666;">Selecciona quién entregará esta carta:</p>

        <form action="procesar_asignacion.php" method="POST">
            <input type="hidden" name="letter_id" id="letter_id_input">
            
            <select name="tech_id" required>
                <option value="">-- Seleccionar Técnico --</option>
                <?php foreach($tecnicos as $t): ?>
                    <option value="<?= $t['id'] ?>">
                        <?= htmlspecialchars($t['full_name']) ?> 
                        (Zona: <?= $t['community_assigned'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-save">Confirmar Asignación</button>
        </form>
    </div>
</div>

<script>
    // Lógica del Modal
    var modal = document.getElementById("modalAsignar");
    var inputId = document.getElementById("letter_id_input");

    function abrirModal(idCarta) {
        inputId.value = idCarta;
        modal.style.display = "block";
    }

    function cerrarModal() {
        modal.style.display = "none";
    }

    // Lógica del Menú de Columnas
    function toggleDropdown() {
        document.getElementById("colDropdown").classList.toggle("show");
    }

    function toggleCol(colClass) {
        var elements = document.getElementsByClassName(colClass);
        for (var i = 0; i < elements.length; i++) {
            if (elements[i].classList.contains('hidden-col')) {
                elements[i].classList.remove('hidden-col');
            } else {
                elements[i].classList.add('hidden-col');
            }
        }
    }

    // Cerrar menú al hacer clic fuera
    window.onclick = function(event) {
        if (event.target == modal) { cerrarModal(); }
        if (!event.target.matches('.dropdown-btn') && !event.target.closest('.dropdown-content')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }
</script>

</body>
</html>