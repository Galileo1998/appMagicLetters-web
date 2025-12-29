<?php
session_start();
require '../db_config.php';

// --- (LÓGICA PHP DE FILTROS IGUAL QUE ANTES) ---
$stmt = $pdo->query("SELECT DISTINCT village FROM letters ORDER BY village");
$lista_comunidades = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("SELECT id, full_name FROM technicians ORDER BY full_name");
$lista_tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$where_clauses = ["1=1"];
$params = [];

// Filtros
if (!empty($_GET['village'])) { $where_clauses[] = "l.village = ?"; $params[] = $_GET['village']; }
if (!empty($_GET['tech_id'])) { $where_clauses[] = "l.tech_id = ?"; $params[] = $_GET['tech_id']; }
if (!empty($_GET['status']))  { $where_clauses[] = "l.status = ?";  $params[] = $_GET['status']; }

$sql_where = implode(" AND ", $where_clauses);

// Consultas
$sql_kpi = "SELECT COUNT(*) as total, SUM(CASE WHEN l.status = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes, SUM(CASE WHEN l.status = 'COMPLETADO' THEN 1 ELSE 0 END) as completadas FROM letters l WHERE $sql_where";
$stmt = $pdo->prepare($sql_kpi); $stmt->execute($params); $stats = $stmt->fetch(PDO::FETCH_ASSOC);

$sql_chart1 = "SELECT l.village, COUNT(*) as cantidad FROM letters l WHERE $sql_where GROUP BY l.village ORDER BY cantidad DESC LIMIT 10";
$stmt = $pdo->prepare($sql_chart1); $stmt->execute($params); $comunidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_chart2 = "SELECT t.full_name, COUNT(l.id) as cantidad FROM technicians t JOIN letters l ON t.id = l.tech_id WHERE $sql_where GROUP BY t.id, t.full_name ORDER BY cantidad DESC";
$stmt = $pdo->prepare($sql_chart2); $stmt->execute($params); $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $stats['total'] > 0 ? $stats['total'] : 1;
$porcentaje = round(($stats['completadas'] / $total) * 100);

// JSON
$json_comunidades_label = json_encode(array_column($comunidades, 'village'));
$json_comunidades_data  = json_encode(array_column($comunidades, 'cantidad'));
$json_tecnicos_label = json_encode(array_column($tecnicos, 'full_name'));
$json_tecnicos_data  = json_encode(array_column($tecnicos, 'cantidad'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - MagicLetter</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; padding-bottom: 40px; }
        /* El padding-top lo maneja el navbar si fuera fixed, pero en este caso es sticky */
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

        /* Estilos específicos del Dashboard (el resto está en navbar) */
        .filter-bar { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; font-weight: bold; color: #666; margin-bottom: 5px; }
        .filter-group select { padding: 8px; border: 1px solid #ddd; border-radius: 5px; min-width: 180px; }
        .btn-filter { background: #1e62d0; color: white; border: none; padding: 9px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-reset { background: #6c757d; color: white; text-decoration: none; padding: 9px 15px; border-radius: 5px; font-size: 14px; }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-left: 5px solid #ccc; }
        .kpi-title { font-size: 13px; color: #888; text-transform: uppercase; font-weight: bold; }
        .kpi-value { font-size: 36px; font-weight: 800; color: #333; margin: 5px 0; }
        
        .kpi-blue { border-color: #1e62d0; } .kpi-yellow { border-color: #ffc107; }
        .kpi-green { border-color: #28a745; } .kpi-purple { border-color: #6f42c1; }

        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .chart-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">

    <form class="filter-bar" method="GET">
        <div class="filter-group">
            <label>📍 Comunidad</label>
            <select name="village">
                <option value="">Todas las comunidades</option>
                <?php foreach($lista_comunidades as $v): ?>
                    <option value="<?= $v ?>" <?= (!empty($_GET['village']) && $_GET['village'] == $v) ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>👷 Técnico</label>
            <select name="tech_id">
                <option value="">Todos los técnicos</option>
                <?php foreach($lista_tecnicos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (!empty($_GET['tech_id']) && $_GET['tech_id'] == $t['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>📌 Estado</label>
            <select name="status">
                <option value="">Cualquier estado</option>
                <option value="PENDIENTE" <?= (!empty($_GET['status']) && $_GET['status'] == 'PENDIENTE') ? 'selected' : '' ?>>Pendiente</option>
                <option value="COMPLETADO" <?= (!empty($_GET['status']) && $_GET['status'] == 'COMPLETADO') ? 'selected' : '' ?>>Completado</option>
            </select>
        </div>

        <button type="submit" class="btn-filter">Aplicar</button>
        <?php if(!empty($_GET)): ?>
            <a href="dashboard.php" class="btn-reset">Limpiar</a>
        <?php endif; ?>
    </form>

    <div class="kpi-grid">
        <div class="kpi-card kpi-blue">
            <div class="kpi-title">Total Filtrado</div>
            <div class="kpi-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="kpi-card kpi-yellow">
            <div class="kpi-title">Pendientes</div>
            <div class="kpi-value"><?= number_format($stats['pendientes']) ?></div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-title">Entregadas</div>
            <div class="kpi-value"><?= number_format($stats['completadas']) ?></div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-title">% Cumplimiento</div>
            <div class="kpi-value"><?= $porcentaje ?>%</div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h3 style="color:#555; margin-top:0;">Carga de Trabajo (Técnicos)</h3>
            <?php if(empty($tecnicos)): ?>
                <p style="color:#999; text-align:center;">No hay datos.</p>
            <?php else: ?>
                <canvas id="chartTecnicos"></canvas>
            <?php endif; ?>
        </div>

        <div class="chart-card">
            <h3 style="color:#555; margin-top:0;">Estado Actual</h3>
            <div style="height:200px; position:relative;">
                <canvas id="chartEstado"></canvas>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <h3 style="color:#555; margin-top:0;">Distribución por Comunidad</h3>
        <canvas id="chartComunidades" height="80"></canvas>
    </div>

</div>

<script>
    <?php if(!empty($tecnicos)): ?>
    new Chart(document.getElementById('chartTecnicos'), {
        type: 'bar',
        data: { labels: <?= $json_tecnicos_label ?>, datasets: [{ label: 'Cartas', data: <?= $json_tecnicos_data ?>, backgroundColor: '#36a2eb', borderRadius: 4 }] },
        options: { responsive: true }
    });
    <?php endif; ?>

    <?php if($stats['total'] > 0): ?>
    new Chart(document.getElementById('chartEstado'), {
        type: 'doughnut',
        data: { labels: ['Pendientes', 'Entregadas'], datasets: [{ data: [<?= $stats['pendientes'] ?>, <?= $stats['completadas'] ?>], backgroundColor: ['#ffc107', '#28a745'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    <?php endif; ?>

    new Chart(document.getElementById('chartComunidades'), {
        type: 'bar',
        data: { labels: <?= $json_comunidades_label ?>, datasets: [{ label: 'Cartas', data: <?= $json_comunidades_data ?>, backgroundColor: '#9966ff', borderRadius: 4 }] },
        options: { indexAxis: 'y', responsive: true }
    });
</script>

</body>
</html>