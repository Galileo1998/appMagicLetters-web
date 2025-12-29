<?php
session_start();
require '../db_config.php';

// Obtener lista de técnicos
$stmt = $pdo->query("SELECT * FROM technicians ORDER BY id DESC");
$tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Técnicos</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 1000px; margin: auto; }
        
        /* Tarjeta Principal */
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { margin: 0; color: #1e62d0; }
        
        /* Botones */
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-add { background: #28a745; color: white; }
        .btn-back { background: #6c757d; color: white; }
        .btn-edit { background: #ffc107; color: #333; padding: 5px 10px; font-size: 12px; }
        .btn-delete { background: #dc3545; color: white; padding: 5px 10px; font-size: 12px; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8f9fa; color: #333; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-activo { background: #d4edda; color: #155724; }
        .badge-inactivo { background: #f8d7da; color: #721c24; }

        /* Modal (Ventana Emergente) */
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 10% auto; padding: 25px; border-radius: 10px; width: 400px; position: relative; }
        .close { float: right; font-size: 24px; cursor: pointer; color: #aaa; }
        .close:hover { color: black; }
        
        /* Formulario Modal */
        label { display: block; margin-top: 10px; font-weight: bold; color: #555; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { background: #1e62d0; color: white; width: 100%; margin-top: 20px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="card">
        <div class="header">
            <div>
                <h1>👷 Catálogo de Técnicos</h1>
                <p style="color:#666;">Gestiona al personal de campo</p>
            </div>
            <div>
                <a href="admin_panel.php" class="btn btn-back">← Volver</a>
                <button onclick="abrirModal()" class="btn btn-add">+ Nuevo Técnico</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Teléfono</th>
                    <th>Zona Asignada</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tecnicos as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['full_name']) ?></td>
                    <td><?= htmlspecialchars($t['phone']) ?></td>
                    <td><?= htmlspecialchars($t['community_assigned']) ?></td>
                    <td>
                        <span class="badge <?= $t['status'] == 'ACTIVO' ? 'badge-activo' : 'badge-inactivo' ?>">
                            <?= $t['status'] ?>
                        </span>
                    </td>
                    <td>
                        <button onclick='editar(<?= json_encode($t) ?>)' class="btn btn-edit">✏️</button>
                        <a href="eliminar_tecnico.php?id=<?= $t['id'] ?>" onclick="return confirm('¿Seguro?')" class="btn btn-delete">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalTecnico" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2 id="modalTitle">Nuevo Técnico</h2>
        
        <form action="guardar_tecnico.php" method="POST">
            <input type="hidden" name="id" id="tec_id">
            
            <label>Nombre Completo:</label>
            <input type="text" name="full_name" id="tec_name" required>
            
            <label>Teléfono:</label>
            <input type="text" name="phone" id="tec_phone">
            
            <label>Comunidad / Zona Principal:</label>
            <input type="text" name="community_assigned" id="tec_community">
            
            <label>Estado:</label>
            <select name="status" id="tec_status">
                <option value="ACTIVO">Activo</option>
                <option value="INACTIVO">Inactivo</option>
            </select>
            
            <button type="submit" class="btn btn-save">Guardar Datos</button>
        </form>
    </div>
</div>

<script>
    var modal = document.getElementById("modalTecnico");

    function abrirModal() {
        // Limpiar formulario para uno nuevo
        document.getElementById('modalTitle').innerText = "Nuevo Técnico";
        document.getElementById('tec_id').value = "";
        document.getElementById('tec_name').value = "";
        document.getElementById('tec_phone').value = "";
        document.getElementById('tec_community').value = "";
        document.getElementById('tec_status').value = "ACTIVO";
        modal.style.display = "block";
    }

    function editar(tecnico) {
        // Llenar formulario con datos existentes
        document.getElementById('modalTitle').innerText = "Editar Técnico";
        document.getElementById('tec_id').value = tecnico.id;
        document.getElementById('tec_name').value = tecnico.full_name;
        document.getElementById('tec_phone').value = tecnico.phone;
        document.getElementById('tec_community').value = tecnico.community_assigned;
        document.getElementById('tec_status').value = tecnico.status;
        modal.style.display = "block";
    }

    function cerrarModal() {
        modal.style.display = "none";
    }

    // Cerrar si clic fuera del modal
    window.onclick = function(event) {
        if (event.target == modal) { cerrarModal(); }
    }
</script>

</body>
</html>