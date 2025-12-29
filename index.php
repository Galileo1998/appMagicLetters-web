<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header("Location: admin/admin_panel.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Magic Letters</title>
    <style>
        body { font-family: sans-serif; background: #1e62d0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 300px; }
        h2 { text-align: center; color: #333; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #1e62d0; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .error { color: red; font-size: 0.8rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Magic Letters</h2>
        <?php if (isset($_GET['error'])): ?>
            <p class="error">Credenciales incorrectas</p>
        <?php endif; ?>
        <form action="auth.php" method="POST">
            <input type="email" name="email" placeholder="Correo electrónico" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>