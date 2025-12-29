<?php
// Obtener el nombre del archivo actual para saber qué botón activar
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* Estilos de la Barra de Navegación */
    .main-header {
        background-color: #1e62d0;
        padding: 0 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 60px;
        position: sticky; /* Se queda fijo arriba al bajar */
        top: 0;
        z-index: 1000;
    }

    .brand-logo {
        color: white;
        font-size: 20px;
        font-weight: 800;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .nav-links {
        display: flex;
        gap: 5px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .nav-item a {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        padding: 8px 15px;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-item a:hover {
        background-color: rgba(255,255,255,0.1);
        color: white;
    }

    /* Estilo para el botón de la página actual */
    .nav-item a.active {
        background-color: white;
        color: #1e62d0;
        font-weight: bold;
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .btn-logout {
        background-color: rgba(0,0,0,0.2);
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 13px;
    }
    .btn-logout:hover { background-color: #dc3545; }

    /* Responsivo para móviles */
    @media (max-width: 768px) {
        .main-header { flex-direction: column; height: auto; padding: 10px; }
        .nav-links { flex-wrap: wrap; justify-content: center; margin-top: 10px; }
        .user-menu { margin-top: 10px; }
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<nav class="main-header">
    <a href="dashboard.php" class="brand-logo">
        <i class="fa-solid fa-envelope-open-text"></i> MagicLetter
    </a>

    <ul class="nav-links">
        <li class="nav-item">
            <a href="dashboard.php" class="<?= $pagina_actual == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="admin_panel.php" class="<?= $pagina_actual == 'admin_panel.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-list"></i> Cartas
            </a>
        </li>
        <li class="nav-item">
            <a href="asignaciones.php" class="<?= $pagina_actual == 'asignaciones.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-check"></i> Asignar
            </a>
        </li>
        <li class="nav-item">
            <a href="tecnicos.php" class="<?= $pagina_actual == 'tecnicos.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i> Técnicos
            </a>
        </li>
        <li class="nav-item">
            <a href="revisar_carga.php" class="<?= $pagina_actual == 'revisar_carga.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-cloud-arrow-up"></i> Carga PDF
            </a>
        </li>
    </ul>

    <div class="user-menu">
        <a href="../tecnico/index.php" target="_blank" style="color:white; font-size:12px; text-decoration:none; opacity:0.7;">
            Ver como Técnico <i class="fa-solid fa-external-link-alt"></i>
        </a>
        <a href="logout.php" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Salir
        </a>
    </div>
</nav>