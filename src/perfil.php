<?php
require_once "includes/app.php";
require_login();

$alert = '';
if (es_post()) {
    csrf_check();
    if (modo_demo()) {
        $alert = alerta('warning', 'En el modo demo no se pueden cambiar contraseñas.');
    } else {
        $actual = (string)($_POST['actual'] ?? '');
        $nueva = (string)($_POST['nueva'] ?? '');
        $repetir = (string)($_POST['repetir'] ?? '');
        $usuario = db_one('SELECT usuario, clave FROM usuario WHERE idusuario = ?', [usuario_id()]);
        $hash = (string)$usuario['clave'];
        $correcta = password_verify($actual, $hash) || (strlen($hash) === 32 && hash_equals($hash, md5($actual)));
        if (!$correcta) {
            $alert = alerta('danger', 'La contraseña actual es incorrecta');
            auditar('clave_cambio_fallido');
        } elseif ($nueva !== $repetir) {
            $alert = alerta('danger', 'Las contraseñas nuevas no coinciden');
        } elseif ($nueva === $actual) {
            $alert = alerta('danger', 'La nueva contraseña debe ser distinta de la actual');
        } elseif ($error = validar_clave_nueva($nueva, $usuario['usuario'])) {
            $alert = alerta('danger', $error);
        } else {
            db_exec('UPDATE usuario SET clave = ? WHERE idusuario = ?', [password_hash($nueva, PASSWORD_DEFAULT), usuario_id()]);
            unset($_SESSION['clave_insegura']);
            session_regenerate_id(true);
            auditar('clave_cambiada');
            flash('success', 'Contraseña cambiada. Úsala la próxima vez que ingreses.');
            redirigir('index.php');
        }
    }
}
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-md-7 col-lg-5 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="fas fa-key"></i> Cambiar contraseña</div>
            <div class="card-body">
                <?php if (!empty($_SESSION['clave_insegura']) && !modo_demo()) { ?>
                    <div class="alert alert-warning">Tu contraseña actual es la de fábrica o muy fácil de adivinar. Para seguir usando el sistema, cámbiala ahora.</div>
                <?php } ?>
                <?php echo $alert; ?>
                <form method="post" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="actual">Contraseña actual</label>
                        <input type="password" name="actual" id="actual" class="form-control" autocomplete="current-password" required>
                    </div>
                    <div class="form-group">
                        <label for="nueva">Contraseña nueva</label>
                        <input type="password" name="nueva" id="nueva" class="form-control" minlength="<?php echo CLAVE_MINIMA; ?>" autocomplete="new-password" required>
                        <small class="text-muted">Mínimo <?php echo CLAVE_MINIMA; ?> caracteres. Mejor una frase fácil de recordar, ej: "papas con aji 2026".</small>
                    </div>
                    <div class="form-group">
                        <label for="repetir">Repite la contraseña nueva</label>
                        <input type="password" name="repetir" id="repetir" class="form-control" minlength="<?php echo CLAVE_MINIMA; ?>" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary btn-lg btn-block"><i class="fas fa-save"></i> Guardar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
