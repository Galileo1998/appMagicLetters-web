<?php
session_start();

// VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión de admin, mandar al login principal
    header("Location: ../index.php");
    exit;
}

require '../db_config.php';

// --- 1. CONFIGURACIÓN DE FILTROS INTELIGENTES ---
$where_clauses = ["1=1"];
$params = [];

// Filtros básicos
if (!empty($_GET['village'])) { $where_clauses[] = "l.village = ?"; $params[] = $_GET['village']; }
if (!empty($_GET['tech_id'])) { $where_clauses[] = "l.tech_id = ?"; $params[] = $_GET['tech_id']; }
if (!empty($_GET['status']))  { $where_clauses[] = "l.status = ?";  $params[] = $_GET['status']; }

// ⚠️ CORRECCIÓN DE FECHAS: Convertimos el texto '04-Jan-2026' a Fecha SQL real para filtrar
if (!empty($_GET['start_date'])) { 
    $where_clauses[] = "STR_TO_DATE(l.due_date, '%d-%b-%Y') >= ?"; 
    $params[] = $_GET['start_date']; 
}
if (!empty($_GET['end_date'])) { 
    $where_clauses[] = "STR_TO_DATE(l.due_date, '%d-%b-%Y') <= ?"; 
    $params[] = $_GET['end_date']; 
}

$sql_where = implode(" AND ", $where_clauses);

// --- 2. KPI GLOBALES ---
$sql_kpi = "SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN l.status IN ('PENDIENTE', 'ASSIGNED') THEN 1 ELSE 0 END) as pendientes, 
    SUM(CASE WHEN l.status IN ('COMPLETADO', 'SYNCED') THEN 1 ELSE 0 END) as completadas,
    SUM(CASE WHEN l.status = 'RETURNED' THEN 1 ELSE 0 END) as devueltas
    FROM letters l WHERE $sql_where";
$stmt = $pdo->prepare($sql_kpi); $stmt->execute($params); $stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Cálculos de Eficiencia
$total = $stats['total'] > 0 ? $stats['total'] : 1;
$tasa_exito = round(($stats['completadas'] / $total) * 100, 1);
$tasa_error = round(($stats['devueltas'] / $total) * 100, 1);

// --- 3. DATOS PARA GRÁFICOS ---

// A. Productividad por Técnico
$sql_chart_tech = "SELECT t.full_name, 
    COUNT(CASE WHEN l.status IN ('COMPLETADO', 'SYNCED') THEN 1 END) as terminadas,
    COUNT(CASE WHEN l.status IN ('PENDIENTE', 'ASSIGNED') THEN 1 END) as pendientes
    FROM technicians t 
    JOIN letters l ON t.id = l.tech_id 
    WHERE $sql_where 
    GROUP BY t.id, t.full_name 
    ORDER BY terminadas DESC LIMIT 10";
$stmt = $pdo->prepare($sql_chart_tech); $stmt->execute($params); $tech_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// B. Cronograma de Vencimientos (Corregido con STR_TO_DATE para ordenar bien)
$sql_timeline = "SELECT l.due_date, COUNT(*) as cantidad 
    FROM letters l 
    WHERE $sql_where AND l.due_date IS NOT NULL 
    GROUP BY l.due_date 
    ORDER BY STR_TO_DATE(l.due_date, '%d-%b-%Y') ASC LIMIT 15";
$stmt = $pdo->prepare($sql_timeline); $stmt->execute($params); $timeline_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// C. Top Comunidades
$sql_village = "SELECT l.village, COUNT(*) as cantidad FROM letters l WHERE $sql_where GROUP BY l.village ORDER BY cantidad DESC LIMIT 8";
$stmt = $pdo->prepare($sql_village); $stmt->execute($params); $village_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 4. TABLA DE EVALUACIÓN DE DESEMPEÑO (NUEVO) ---
$sql_performance = "SELECT t.full_name,
    COUNT(l.id) as total_asignado,
    SUM(CASE WHEN l.status IN ('COMPLETADO','SYNCED') THEN 1 ELSE 0 END) as completado,
    SUM(CASE WHEN l.status IN ('PENDIENTE','ASSIGNED') THEN 1 ELSE 0 END) as pendiente,
    SUM(CASE WHEN l.status = 'RETURNED' THEN 1 ELSE 0 END) as devuelto
    FROM technicians t
    JOIN letters l ON t.id = l.tech_id
    WHERE $sql_where
    GROUP BY t.id, t.full_name
    ORDER BY completado DESC";
$stmt = $pdo->prepare($sql_performance); $stmt->execute($params); $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar JSONs para JS
$js_tech_labels = json_encode(array_column($tech_data, 'full_name'));
$js_tech_done   = json_encode(array_column($tech_data, 'terminadas'));
$js_tech_pend   = json_encode(array_column($tech_data, 'pendientes'));
$js_time_labels = json_encode(array_column($timeline_data, 'due_date'));
$js_time_data   = json_encode(array_column($timeline_data, 'cantidad'));
$js_village_labels = json_encode(array_column($village_data, 'village'));
$js_village_data   = json_encode(array_column($village_data, 'cantidad'));

// Listas para selects
$lista_comunidades = $pdo->query("SELECT DISTINCT village FROM letters ORDER BY village")->fetchAll(PDO::FETCH_COLUMN);
$lista_tecnicos = $pdo->query("SELECT id, full_name FROM technicians ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Evaluativo - MagicLetter</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --color-primary: #46B094;
            --color-support: #34859B;
            --color-accent: #B4D6E0;
            --color-bg: #f4f7f6;
            --color-text: #444;
        }

        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); margin: 0; color: var(--color-text); padding-bottom: 60px; }
        .container { max-width: 1300px; margin: 30px auto; padding: 0 20px; }

        /* CABECERA */
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        h1 { color: var(--color-primary); margin: 0; font-weight: 800; font-size: 26px; }

        /* BARRA DE FILTROS */
        .filter-container { 
            background: white; padding: 25px; border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 30px; 
            border-top: 5px solid var(--color-primary);
        }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; align-items: end; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--color-support); margin-bottom: 5px; display: block; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        
        .btn-filter { background: var(--color-primary); color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; transition: 0.3s; }
        .btn-filter:hover { background: var(--color-support); }
        .btn-reset { background: #e2e6ea; color: #555; text-decoration: none; padding: 10px; border-radius: 6px; text-align: center; font-size: 14px; font-weight: 600; display: block; }

        /* KPIs */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid transparent; position: relative; }
        .kpi-icon { position: absolute; right: 20px; top: 20px; font-size: 30px; opacity: 0.15; }
        .kpi-title { font-size: 12px; color: #888; font-weight: 700; text-transform: uppercase; }
        .kpi-value { font-size: 32px; font-weight: 800; margin: 5px 0; color: #333; }
        
        .kpi-done { border-color: var(--color-primary); } .kpi-done .kpi-value { color: var(--color-primary); }
        .kpi-pending { border-color: #f0ad4e; } .kpi-pending .kpi-value { color: #f0ad4e; }
        .kpi-return { border-color: #dc3545; } .kpi-return .kpi-value { color: #dc3545; }

        /* GRÁFICOS */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-bottom: 30px; }
        .chart-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .chart-title { font-weight: 700; color: var(--color-support); margin-top: 0; font-size: 16px; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px; }

        /* TABLA DE EVALUACIÓN */
        .eval-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .eval-table th { background: var(--color-accent); color: var(--color-support); padding: 12px; text-align: left; }
        .eval-table td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        .eval-table tr:hover { background: #f9fbfb; }
        
        .progress-bar { background: #eee; border-radius: 10px; height: 8px; width: 100px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 5px; }
        .progress-fill { height: 100%; border-radius: 10px; }
        
        @media(max-width: 900px) { .charts-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    
    <div class="dashboard-header">
        <h1>Dashboard de Resultados</h1>
        <small style="color:#666;">Datos actualizados al <?= date('d/m/Y H:i') ?></small>
    </div>

    <div class="filter-container">
        <form class="filter-form" method="GET">
            <div class="form-group">
                <label>📅 Desde (Vencimiento)</label>
                <input type="date" name="start_date" class="form-control" value="<?= $_GET['start_date'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>📅 Hasta (Vencimiento)</label>
                <input type="date" name="end_date" class="form-control" value="<?= $_GET['end_date'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>📍 Comunidad</label>
                <select name="village" class="form-control">
                    <option value="">-- Todas --</option>
                    <?php foreach($lista_comunidades as $v): ?>
                        <option value="<?= $v ?>" <?= ($_GET['village'] ?? '') == $v ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>👷 Técnico</label>
                <select name="tech_id" class="form-control">
                    <option value="">-- Todos --</option>
                    <?php foreach($lista_tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($_GET['tech_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= $t['full_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filtrar Datos</button>
            </div>
            <?php if(!empty($_GET)): ?>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <a href="dashboard.php" class="btn-reset">Limpiar</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-left: 5px solid #666;">
            <i class="fa-solid fa-layer-group kpi-icon"></i>
            <div class="kpi-title">Total Cartas</div>
            <div class="kpi-value"><?= number_format($stats['total']) ?></div>
            <div class="kpi-sub">Volumen total</div>
        </div>
        <div class="kpi-card kpi-done">
            <i class="fa-solid fa-check-circle kpi-icon"></i>
            <div class="kpi-title">Efectividad</div>
            <div class="kpi-value"><?= $tasa_exito ?>%</div>
            <div class="kpi-sub" style="color:var(--color-primary);"><?= $stats['completadas'] ?> cartas listas</div>
        </div>
        <div class="kpi-card kpi-pending">
            <i class="fa-solid fa-hourglass-half kpi-icon"></i>
            <div class="kpi-title">Pendientes</div>
            <div class="kpi-value"><?= number_format($stats['pendientes']) ?></div>
            <div class="kpi-sub">En proceso de campo</div>
        </div>
        <div class="kpi-card kpi-return">
            <i class="fa-solid fa-triangle-exclamation kpi-icon"></i>
            <div class="kpi-title">Tasa de Rechazo</div>
            <div class="kpi-value"><?= $tasa_error ?>%</div>
            <div class="kpi-sub" style="color:#dc3545;"><?= $stats['devueltas'] ?> cartas devueltas</div>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-card">
            <h3 class="chart-title">📊 Productividad Comparativa</h3>
            <canvas id="chartTecnicos" height="130"></canvas>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">📈 Tendencia de Vencimientos</h3>
            <canvas id="chartTimeline" height="130"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3 class="chart-title">🏆 Evaluación de Desempeño por Técnico</h3>
        <div style="overflow-x:auto;">
            <table class="eval-table">
                <thead>
                    <tr>
                        <th>Técnico</th>
                        <th style="text-align:center;">Asignadas</th>
                        <th style="text-align:center;">Completadas</th>
                        <th style="text-align:center;">Pendientes</th>
                        <th style="text-align:center;">Devueltas</th>
                        <th>Nivel de Eficiencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($performance_data as $p): 
                        $total_p = $p['total_asignado'] > 0 ? $p['total_asignado'] : 1;
                        $eficiencia = round(($p['completado'] / $total_p) * 100);
                        
                        // Color de barra según eficiencia
                        $bar_color = ($eficiencia >= 80) ? '#46B094' : (($eficiencia >= 50) ? '#f0ad4e' : '#dc3545');
                    ?>
                    <tr>
                        <td style="font-weight:bold; color:#555;"><?= htmlspecialchars($p['full_name']) ?></td>
                        <td style="text-align:center;"><?= $p['total_asignado'] ?></td>
                        <td style="text-align:center; color:#46B094; font-weight:bold;"><?= $p['completado'] ?></td>
                        <td style="text-align:center; color:#f0ad4e;"><?= $p['pendiente'] ?></td>
                        <td style="text-align:center; color:#dc3545;"><?= $p['devuelto'] ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?= $eficiencia ?>%; background:<?= $bar_color ?>;"></div>
                            </div>
                            <span style="font-size:12px; font-weight:bold; color:<?= $bar_color ?>;"><?= $eficiencia ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($performance_data)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">Sin datos para mostrar</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    const colorPrimary = '#46B094';
    const colorSupport = '#34859B';
    const colorAccent  = '#B4D6E0';
    const colorPending = '#f0ad4e';

    // 1. Gráfico Técnicos
    new Chart(document.getElementById('chartTecnicos'), {
        type: 'bar',
        data: {
            labels: <?= $js_tech_labels ?>,
            datasets: [
                { label: 'Terminadas', data: <?= $js_tech_done ?>, backgroundColor: colorPrimary, borderRadius: 4 },
                { label: 'Pendientes', data: <?= $js_tech_pend ?>, backgroundColor: colorAccent, borderRadius: 4 }
            ]
        },
        options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true } } }
    });

    // 2. Gráfico Línea de Tiempo
    new Chart(document.getElementById('chartTimeline'), {
        type: 'line',
        data: {
            labels: <?= $js_time_labels ?>,
            datasets: [{
                label: 'Volumen de Vencimiento',
                data: <?= $js_time_data ?>,
                borderColor: colorSupport,
                backgroundColor: 'rgba(52, 133, 155, 0.1)',
                borderWidth: 2, fill: true, tension: 0.4
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
</script>

</body>
</html>