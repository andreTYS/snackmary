<?php
require_once "includes/app.php";
require_permiso('configuracion');

$alert = '';
if (es_post()) {
    csrf_check();
    $nombre = texto($_POST['nombre'] ?? '', 100);
    $telefono = texto($_POST['telefono'] ?? '', 15);
    $email = texto($_POST['email'] ?? '', 100);
    $direccion = texto($_POST['direccion'] ?? '', 500);
    $whatsapp = preg_replace('/\D+/', '', (string)($_POST['whatsapp'] ?? ''));
    $codigoPais = preg_replace('/\D+/', '', (string)($_POST['codigo_pais'] ?? '51')) ?: '51';
    if ($nombre === '' || $telefono === '' || $email === '' || $direccion === '') {
        $alert = alerta('danger', 'Todos los campos son obligatorios');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = alerta('danger', 'El correo no es válido');
    } else {
        $id = (int)db_val('SELECT id FROM configuracion ORDER BY id LIMIT 1');
        db_exec(
            'UPDATE configuracion SET nombre = ?, telefono = ?, email = ?, direccion = ?, whatsapp = ?, codigo_pais = ? WHERE id = ?',
            [$nombre, $telefono, $email, $direccion, substr($whatsapp, 0, 20), substr($codigoPais, 0, 5), $id]
        );
        flash('success', 'Datos modificados');
        redirigir('config.php');
    }
}
$data = config_negocio();
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Datos de la Empresa</div>
            <div class="card-body">
                <form action="" method="post" class="p-3">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="txtNombre">Nombre:</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo e($data['nombre'] ?? ''); ?>" id="txtNombre" placeholder="Nombre de la empresa" required>
                    </div>
                    <div class="form-group">
                        <label for="txtTelEmpresa">Teléfono:</label>
                        <input type="tel" name="telefono" class="form-control" value="<?php echo e($data['telefono'] ?? ''); ?>" id="txtTelEmpresa" placeholder="Teléfono de la empresa" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-4">
                            <label for="txtPais">Código país:</label>
                            <input type="text" name="codigo_pais" class="form-control" value="<?php echo e($data['codigo_pais'] ?? '51'); ?>" id="txtPais" placeholder="51">
                        </div>
                        <div class="form-group col-8">
                            <label for="txtWhatsapp"><i class="fab fa-whatsapp"></i> WhatsApp del negocio:</label>
                            <input type="tel" name="whatsapp" class="form-control" value="<?php echo e($data['whatsapp'] ?? ''); ?>" id="txtWhatsapp" placeholder="987654321">
                            <small class="text-muted">Aquí llegan los pedidos hechos desde la carta pública.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="txtEmailEmpresa">Correo electrónico:</label>
                        <input type="email" name="email" class="form-control" value="<?php echo e($data['email'] ?? ''); ?>" id="txtEmailEmpresa" placeholder="Correo de la empresa" required>
                    </div>
                    <div class="form-group">
                        <label for="txtDirEmpresa">Dirección:</label>
                        <input type="text" name="direccion" class="form-control" value="<?php echo e($data['direccion'] ?? ''); ?>" id="txtDirEmpresa" placeholder="Dirección de la empresa" required>
                    </div>
                    <?php echo $alert; ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Modificar datos</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
