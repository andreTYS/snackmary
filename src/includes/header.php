<?php
require_once __DIR__ . '/app.php';
require_login();
$paginaActual = basename($_SERVER['SCRIPT_NAME']);
$menu = [
    ['index.php', 'fas fa-tachometer-alt', 'Inicio', null],
    ['ventas.php', 'fas fa-cash-register', 'Nueva venta', 'nueva_venta'],
    ['mesas.php', 'fas fa-chair', 'Mesas', 'mesas'],
    ['pedidos.php', 'fas fa-concierge-bell', 'Pedidos', 'pedidos'],
    ['cocina.php', 'fas fa-fire', 'Cocina', 'cocina'],
    ['repartidor.php', 'fas fa-motorcycle', 'Mis entregas', 'repartidor'],
    ['caja.php', 'fas fa-cash-register', 'Caja', 'caja'],
    ['lista_ventas.php', 'fas fa-receipt', 'Ventas', 'ventas'],
    ['reportes.php', 'fas fa-chart-bar', 'Reportes', 'reportes'],
    ['productos.php', 'fas fa-hamburger', 'Productos', 'productos'],
    ['categorias.php', 'fas fa-tags', 'Categorías', 'productos'],
    ['clientes.php', 'fas fa-users', 'Clientes', 'clientes'],
    ['usuarios.php', 'fas fa-user', 'Usuarios', 'usuarios'],
    ['config.php', 'fas fa-cogs', 'Configuración', 'configuracion'],
];
$cajaActual = tiene_permiso('caja') || tiene_permiso('nueva_venta') ? caja_abierta() : null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>" />
    <title>Snack Mary - Panel</title>
    <link href="../assets/css/styles.css" rel="stylesheet" />
    <link href="../assets/css/dataTables.bootstrap4.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/js/jquery-ui/jquery-ui.min.css">
    <link href="../assets/css/tablet.css?v=4" rel="stylesheet" />
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="manifest" href="../manifest.webmanifest">
    <meta name="theme-color" content="#e21e24">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="../assets/img/icon-192.png">
    <script src="../assets/js/all.min.js"></script>
</head>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand" href="index.php"><img src="../assets/img/icon-192.png" width="32" height="32" class="rounded-circle mr-2" alt="">SNACK MARY</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0" id="sidebarToggle" href="#"><i class="fas fa-bars"></i></button>
        <ul class="navbar-nav ml-auto align-items-center">
            <?php if (tiene_permiso('caja')) { ?>
                <li class="nav-item mr-2 d-none d-sm-block">
                    <a href="caja.php" class="badge badge-pill p-2 badge-<?php echo $cajaActual ? 'success' : 'danger'; ?>">
                        <i class="fas fa-cash-register"></i> <?php echo $cajaActual ? 'Caja abierta' : 'Caja cerrada'; ?>
                    </a>
                </li>
            <?php } ?>
            <li class="nav-item mr-1">
                <button type="button" class="btn btn-link nav-link" id="btnPantallaCompleta" title="Pantalla completa"><i class="fas fa-expand"></i></button>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user fa-fw"></i> <?php echo e($_SESSION['nombre'] ?? ''); ?></a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#nuevo_pass">Cambiar contraseña</a>
                    <a class="dropdown-item" href="../carta.php" target="_blank">Ver carta pública</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="salir.php">Cerrar Sesión</a>
                </div>
            </li>
        </ul>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <?php foreach ($menu as [$url, $icono, $texto, $permiso]) {
                            if ($permiso !== null && !tiene_permiso($permiso)) continue; ?>
                            <a class="nav-link<?php echo $paginaActual === $url ? ' active' : ''; ?>" href="<?php echo $url; ?>">
                                <div class="sb-nav-link-icon"><i class="<?php echo $icono; ?>"></i></div>
                                <?php echo e($texto); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid mt-2">
                    <?php if (!empty($_SESSION['clave_insegura'])) { ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> Estás usando la contraseña por defecto. Cámbiala ahora desde el menú de usuario &rarr; <b>Cambiar contraseña</b>.
                        </div>
                    <?php } ?>
                    <?php echo flash(); ?>
