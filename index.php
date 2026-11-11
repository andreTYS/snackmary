<?php
ob_start();
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
if (!empty($_SESSION['active'])) {
    header('Location: src/');
    exit;
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');
    $intentos = $_SESSION['intentos'] ?? 0;
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $alert = 'La sesión expiró, intenta de nuevo.';
    } elseif ($usuario === '' || $clave === '') {
        $alert = 'Ingrese su usuario y su clave';
    } elseif ($intentos >= 5 && time() - ($_SESSION['ultimo_intento'] ?? 0) < 60) {
        $alert = 'Demasiados intentos. Espera un minuto.';
    } else {
        require_once 'conexion.php';
        $stmt = $conexion->prepare('SELECT idusuario, nombre, usuario, clave FROM usuario WHERE usuario = ? AND estado = 1 LIMIT 1');
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $dato = $stmt->get_result()->fetch_assoc();

        $valida = false;
        if ($dato) {
            $hash = $dato['clave'];
            if (password_verify($clave, $hash)) {
                $valida = true;
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $nuevo = password_hash($clave, PASSWORD_DEFAULT);
                }
            } elseif (strlen($hash) === 32 && hash_equals($hash, md5($clave))) {
                // Contraseña antigua en MD5: se migra a password_hash automáticamente
                $valida = true;
                $nuevo = password_hash($clave, PASSWORD_DEFAULT);
            }
            if ($valida && isset($nuevo)) {
                $up = $conexion->prepare('UPDATE usuario SET clave = ? WHERE idusuario = ?');
                $up->bind_param('si', $nuevo, $dato['idusuario']);
                $up->execute();
            }
        }

        if ($valida) {
            session_regenerate_id(true);
            $_SESSION = [
                'active' => true,
                'idUser' => (int)$dato['idusuario'],
                'nombre' => $dato['nombre'],
                'user'   => $dato['usuario'],
                'csrf'   => bin2hex(random_bytes(32)),
                'clave_insegura' => in_array($clave, ['admin', '123456', '12345', 'vendedor'], true),
            ];
            header('Location: src/');
            exit;
        }
        $_SESSION['intentos'] = $intentos + 1;
        $_SESSION['ultimo_intento'] = time();
        $alert = 'Usuario o contraseña incorrecta';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Snack Mary - Iniciar sesión</title>
    <link href="assets/css/styles.css" rel="stylesheet" />
    <link rel="icon" href="assets/img/logo.png">
    <script src="assets/js/all.min.js"></script>
</head>

<body class="bg-primary">
    <div id="layoutAuthentication">
        <div id="layoutAuthentication_content">
            <main>
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-5">
                            <div class="card shadow-lg border-0 rounded-lg mt-5">
                                <div class="card-header text-center">
                                    <img class="img-thumbnail" src="assets/img/logo.png" width="100" alt="Snack Mary">
                                    <h3 class="font-weight-light my-4">Iniciar Sesión</h3>
                                </div>
                                <div class="card-body">
                                    <form action="" method="POST">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                                        <div class="form-group">
                                            <label class="small mb-1" for="usuario"><i class="fas fa-user"></i> Usuario</label>
                                            <input class="form-control py-4" id="usuario" name="usuario" type="text" placeholder="Ingrese usuario" autocomplete="username" required />
                                        </div>
                                        <div class="form-group">
                                            <label class="small mb-1" for="clave"><i class="fas fa-key"></i> Contraseña</label>
                                            <input class="form-control py-4" id="clave" name="clave" type="password" placeholder="Ingrese contraseña" autocomplete="current-password" required />
                                        </div>
                                        <?php if ($alert) { ?>
                                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($alert); ?></div>
                                        <?php } ?>
                                        <div class="form-group d-flex align-items-center justify-content-between mt-4 mb-0">
                                            <a class="small" href="carta.php"><i class="fas fa-utensils"></i> Ver la carta</a>
                                            <button class="btn btn-primary" type="submit">Ingresar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <div id="layoutAuthentication_footer">
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid">
                    <div class="small text-muted">Copyright &copy; Snack Mary <?php echo date('Y'); ?></div>
                </div>
            </footer>
        </div>
    </div>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
</body>

</html>
