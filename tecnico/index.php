<?php
session_start();
require '../db_config.php';

// Si ya está logueado, mandar al panel
if (isset($_SESSION['tech_id'])) {
    header("Location: panel.php");
    exit;
}

// Obtener lista de técnicos para el select
$stmt = $pdo->query("SELECT id, full_name FROM technicians WHERE status = 'ACTIVO'");
$tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tech_id = $_POST['tech_id'];
    $phone = trim($_POST['phone']);

    // Verificar si el teléfono coincide con el ID seleccionado
    $sql = "SELECT * FROM technicians WHERE id = ? AND phone = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tech_id, $phone]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['tech_id'] = $user['id'];
        $_SESSION['tech_name'] = $user['full_name'];
        header("Location: panel.php");
        exit;
    } else {
        $error = "❌ Teléfono incorrecto o usuario no válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Acceso Técnico</title>
    <style>
        body { font-family: sans-serif; background: #eef2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 90%; max-width: 350px; text-align: center; }
        h2 { color: #1e62d0; margin-bottom: 20px; }
        select, input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        .btn { background: #1e62d0; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .error { color: red; font-size: 14px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>👋 Hola, Técnico</h2>
        <p style="color:#666;">Ingresa para ver tus cartas</p>
        
        <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>

        <form method="POST">
            <select name="tech_id" required>
                <option value="">Selecciona tu nombre...</option>
                <?php foreach($tecnicos as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <input type="tel" name="phone" placeholder="Ingresa tu teléfono (Contraseña)" required>
            
            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
</body>
</html>