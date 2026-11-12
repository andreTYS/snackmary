<?php
ob_start();
require_once __DIR__ . '/src/includes/seguridad.php';
require_once __DIR__ . '/conexion.php';
iniciar_sesion();
if (!empty($_SESSION['active'])) {
    header('Location: src/');
    exit;
}
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = mb_substr(trim((string)($_POST['usuario'] ?? '')), 0, 50);
    $clave = (string)($_POST['clave'] ?? '');
    $espera = $usuario !== '' ? login_bloqueado($usuario) : 0;
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $alert = 'La sesión expiró, intenta de nuevo.';
    } elseif ($usuario === '' || $clave === '') {
        $alert = 'Ingrese su usuario y su clave';
    } elseif ($espera > 0) {
        $alert = 'Demasiados intentos fallidos. Espera ' . $espera . ' minutos o pide ayuda al administrador.';
        auditar('login_bloqueado', $usuario, null);
    } else {
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
        } else {
            // Mismo tiempo de respuesta exista o no el usuario (no revela qué usuarios existen)
            password_verify($clave, '$2y$10$abcdefghijklmnopqrstuuJ8oXgYq0eG8vYq8L0x9fJ1h3l1i5S6e');
        }

        registrar_intento($usuario, $valida);
        if ($valida) {
            session_regenerate_id(true);
            $_SESSION = [
                'active' => true,
                'idUser' => (int)$dato['idusuario'],
                'nombre' => $dato['nombre'],
                'user'   => $dato['usuario'],
                'csrf'   => bin2hex(random_bytes(32)),
                'ultimo_uso' => time(),
                'clave_insegura' => clave_debil($clave, $dato['usuario']),
            ];
            auditar('login', '', (int)$dato['idusuario']);
            header('Location: src/');
            exit;
        }
        auditar('login_fallido', $usuario, null);
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
                                        <?php if (modo_demo()) { ?>
                                            <div class="alert alert-info small">
                                                <b><i class="fas fa-flask"></i> Demostración.</b> Toca un usuario para entrar. Los datos se reinician cada noche.
                                                <div class="mt-2">
                                                    <?php foreach (DEMO_USUARIOS as [$u, $c, $rol]) { ?>
                                                        <button type="button" class="btn btn-sm btn-light border mb-1 usuario-demo" data-u="<?php echo htmlspecialchars($u); ?>" data-c="<?php echo htmlspecialchars($c); ?>"><b><?php echo htmlspecialchars($u); ?></b> · <?php echo htmlspecialchars($rol); ?></button>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
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
    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        document.querySelectorAll('.usuario-demo').forEach(b => b.addEventListener('click', () => {
            document.getElementById('usuario').value = b.dataset.u;
            document.getElementById('clave').value = b.dataset.c;
            b.closest('form').submit();
        }));
    </script>
</body>

</html>
