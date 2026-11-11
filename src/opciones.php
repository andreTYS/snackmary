<?php
/*
 * Opciones de un plato: grupos (Tipo, Extras, Bebida del combo...) y sus opciones.
 * Cada opción puede sumar precio y vincularse a una bebida para descontar su stock.
 */
require_once "includes/app.php";
require_permiso('productos');

$id = entero($_REQUEST['id'] ?? 0);
$producto = $id ? db_one('SELECT codproducto, descripcion, precio FROM producto WHERE codproducto = ?', [$id]) : null;
if (!$producto) {
    redirigir('productos.php');
}
const TIPOS_GRUPO = [
    'obligatorio' => ['Obligatorio · elige 1', 1, 1],
    'opcional'    => ['Opcional · elige 1', 0, 1],
    'varios'      => ['Opcional · varios', 0, 0],
];
$volver = 'opciones.php?id=' . $id;

/** Comprueba que un grupo / opción pertenezca a este producto. */
function grupo_del_producto(int $idGrupo, int $idProducto): bool
{
    return (bool)db_one('SELECT id FROM opcion_grupos WHERE id = ? AND id_producto = ? AND estado = 1', [$idGrupo, $idProducto]);
}
function opcion_del_producto(int $idOpcion, int $idProducto): bool
{
    return (bool)db_one('SELECT o.id FROM opciones o INNER JOIN opcion_grupos g ON g.id = o.id_grupo WHERE o.id = ? AND g.id_producto = ?', [$idOpcion, $idProducto]);
}
function producto_vinculable($valor): ?int
{
    $v = entero($valor);
    return $v && db_one('SELECT codproducto FROM producto WHERE codproducto = ? AND estado = 1', [$v]) ? $v : null;
}

if (es_post()) {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $idGrupo = entero($_POST['id_grupo'] ?? 0);
    $idOpcion = entero($_POST['id_opcion'] ?? 0);

    if ($accion === 'grupo_guardar') {
        $nombre = texto($_POST['nombre'] ?? '', 60);
        $tipo = array_key_exists($_POST['tipo'] ?? '', TIPOS_GRUPO) ? $_POST['tipo'] : 'opcional';
        [, $min, $max] = TIPOS_GRUPO[$tipo];
        if ($tipo === 'varios') {
            $max = max(2, min(20, entero($_POST['maximo'] ?? 5)));
        }
        if ($nombre === '') {
            flash('danger', 'Escribe el nombre del grupo');
        } elseif ($idGrupo) {
            if (grupo_del_producto($idGrupo, $id)) {
                db_exec('UPDATE opcion_grupos SET nombre = ?, minimo = ?, maximo = ?, orden = ? WHERE id = ?', [$nombre, $min, $max, entero($_POST['orden'] ?? 0), $idGrupo]);
                flash('success', 'Grupo actualizado');
            }
        } else {
            $orden = (int)db_val('SELECT COALESCE(MAX(orden), 0) + 1 FROM opcion_grupos WHERE id_producto = ?', [$id]);
            db_exec('INSERT INTO opcion_grupos (id_producto, nombre, minimo, maximo, orden) VALUES (?, ?, ?, ?, ?)', [$id, $nombre, $min, $max, $orden]);
            flash('success', 'Grupo "' . $nombre . '" creado. Ahora agrégale opciones.');
        }
    } elseif ($accion === 'grupo_eliminar' && grupo_del_producto($idGrupo, $id)) {
        db_exec('UPDATE opcion_grupos SET estado = 0 WHERE id = ?', [$idGrupo]);
        db_exec('UPDATE opciones SET estado = 0 WHERE id_grupo = ?', [$idGrupo]);
        flash('success', 'Grupo eliminado');
    } elseif ($accion === 'opcion_guardar' && grupo_del_producto($idGrupo, $id)) {
        $nombre = texto($_POST['nombre'] ?? '', 60);
        $precio = decimal_positivo($_POST['precio'] ?? '0') ?? 0.0;
        $vinculo = producto_vinculable($_POST['id_producto_stock'] ?? 0);
        if ($nombre === '') {
            flash('danger', 'Escribe el nombre de la opción');
        } elseif ($idOpcion) {
            if (opcion_del_producto($idOpcion, $id)) {
                db_exec('UPDATE opciones SET nombre = ?, precio = ?, id_producto_stock = ? WHERE id = ?', [$nombre, $precio, $vinculo, $idOpcion]);
                flash('success', 'Opción actualizada');
            }
        } else {
            $orden = (int)db_val('SELECT COALESCE(MAX(orden), 0) + 1 FROM opciones WHERE id_grupo = ?', [$idGrupo]);
            db_exec('INSERT INTO opciones (id_grupo, nombre, precio, id_producto_stock, orden) VALUES (?, ?, ?, ?, ?)', [$idGrupo, $nombre, $precio, $vinculo, $orden]);
            flash('success', 'Opción agregada');
        }
    } elseif ($accion === 'opcion_eliminar' && opcion_del_producto($idOpcion, $id)) {
        db_exec('UPDATE opciones SET estado = 0 WHERE id = ?', [$idOpcion]);
        flash('success', 'Opción eliminada');
    } elseif ($accion === 'agregar_categoria' && grupo_del_producto($idGrupo, $id)) {
        // Agrega como opciones todos los productos de una categoría (ej: todas las bebidas), vinculados a su stock
        $idCat = entero($_POST['id_categoria'] ?? 0);
        $precio = decimal_positivo($_POST['precio'] ?? '0') ?? 0.0;
        $n = 0;
        $orden = (int)db_val('SELECT COALESCE(MAX(orden), 0) FROM opciones WHERE id_grupo = ?', [$idGrupo]);
        foreach (db_all('SELECT codproducto, descripcion FROM producto WHERE id_categoria = ? AND estado = 1 AND codproducto <> ? ORDER BY descripcion', [$idCat, $id]) as $p) {
            if (db_one('SELECT id FROM opciones WHERE id_grupo = ? AND id_producto_stock = ? AND estado = 1', [$idGrupo, (int)$p['codproducto']])) {
                continue;
            }
            db_exec('INSERT INTO opciones (id_grupo, nombre, precio, id_producto_stock, orden) VALUES (?, ?, ?, ?, ?)', [$idGrupo, mb_substr($p['descripcion'], 0, 60), $precio, (int)$p['codproducto'], ++$orden]);
            $n++;
        }
        flash($n ? 'success' : 'warning', $n ? "Se agregaron $n opciones" : 'No había productos nuevos en esa categoría');
    } elseif ($accion === 'copiar') {
        // Copia los grupos y opciones de otro plato (se agregan a los que ya tiene)
        $origen = entero($_POST['origen'] ?? 0);
        $grupos = $origen && $origen !== $id ? db_all('SELECT * FROM opcion_grupos WHERE id_producto = ? AND estado = 1 ORDER BY orden, id', [$origen]) : [];
        $conexion->begin_transaction();
        foreach ($grupos as $g) {
            db_exec('INSERT INTO opcion_grupos (id_producto, nombre, minimo, maximo, orden) VALUES (?, ?, ?, ?, ?)', [$id, $g['nombre'], (int)$g['minimo'], (int)$g['maximo'], (int)$g['orden']]);
            $nuevo = db_insert_id();
            foreach (db_all('SELECT * FROM opciones WHERE id_grupo = ? AND estado = 1 ORDER BY orden, id', [(int)$g['id']]) as $o) {
                db_exec('INSERT INTO opciones (id_grupo, nombre, precio, id_producto_stock, orden) VALUES (?, ?, ?, ?, ?)', [$nuevo, $o['nombre'], (float)$o['precio'], $o['id_producto_stock'] !== null ? (int)$o['id_producto_stock'] : null, (int)$o['orden']]);
            }
        }
        $conexion->commit();
        flash($grupos ? 'success' : 'warning', $grupos ? 'Opciones copiadas (' . count($grupos) . ' grupos)' : 'Ese plato no tiene opciones para copiar');
    }
    redirigir($volver);
}

$grupos = db_all('SELECT * FROM opcion_grupos WHERE id_producto = ? AND estado = 1 ORDER BY orden, id', [$id]);
$opcionesPorGrupo = [];
foreach ($grupos ? db_all(
    'SELECT o.*, p.descripcion AS vinculo, p.existencia, p.controla_stock FROM opciones o
     LEFT JOIN producto p ON p.codproducto = o.id_producto_stock
     WHERE o.estado = 1 AND o.id_grupo IN (' . implode(',', array_map(fn($g) => (int)$g['id'], $grupos)) . ') ORDER BY o.orden, o.id'
) : [] as $o) {
    $opcionesPorGrupo[(int)$o['id_grupo']][] = $o;
}
$vinculables = db_all("SELECT p.codproducto, p.descripcion, COALESCE(c.nombre, 'Otros') AS categoria FROM producto p LEFT JOIN categorias c ON c.id = p.id_categoria WHERE p.estado = 1 AND p.codproducto <> ? ORDER BY categoria, p.descripcion", [$id]);
$categorias = db_all('SELECT id, nombre, cocina FROM categorias WHERE estado = 1 ORDER BY orden, nombre');
// Para "agregar todos los productos de...", proponer primero una categoría que no va a cocina (bebidas)
$catBebidas = 0;
foreach ($categorias as $c) {
    if (!(int)$c['cocina']) {
        $catBebidas = (int)$c['id'];
        break;
    }
}
$copiables = db_all('SELECT DISTINCT p.codproducto, p.descripcion FROM producto p INNER JOIN opcion_grupos g ON g.id_producto = p.codproducto AND g.estado = 1 WHERE p.codproducto <> ? AND p.estado = 1 ORDER BY p.descripcion', [$id]);

function tipo_grupo(array $g): string
{
    if ((int)$g['minimo'] >= 1) {
        return 'obligatorio';
    }
    return (int)$g['maximo'] > 1 ? 'varios' : 'opcional';
}
function select_vinculo(array $vinculables, $actual, string $form = ''): string
{
    $html = '<select name="id_producto_stock" class="form-control form-control-sm"' . ($form ? ' form="' . e($form) . '"' : '') . ' aria-label="Descuenta stock de">';
    $html .= '<option value="">— No descuenta stock —</option>';
    $cat = null;
    foreach ($vinculables as $v) {
        if ($v['categoria'] !== $cat) {
            $html .= ($cat !== null ? '</optgroup>' : '') . '<optgroup label="' . e($v['categoria']) . '">';
            $cat = $v['categoria'];
        }
        $html .= '<option value="' . (int)$v['codproducto'] . '"' . ((int)$actual === (int)$v['codproducto'] ? ' selected' : '') . '>' . e($v['descripcion']) . '</option>';
    }
    return $html . ($cat !== null ? '</optgroup>' : '') . '</select>';
}
include_once "includes/header.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="productos.php" class="btn btn-outline-dark btn-sm mb-2"><i class="fas fa-arrow-left"></i> Productos</a>
        <h1 class="h3 mb-0">Opciones de <?php echo e($producto['descripcion']); ?> <small class="text-muted">S/ <?php echo dinero($producto['precio']); ?></small></h1>
        <p class="text-muted mb-0">Ej.: <b>Tipo</b> (clásica, pura papa, con mollejitas), <b>Extras</b> (+ porción de salchicha) o <b>Bebida del combo</b>.</p>
    </div>
    <?php if ($copiables) { ?>
        <form method="post" class="form-inline mt-2">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="copiar">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <select name="origen" class="form-control mr-2" aria-label="Copiar de">
                <?php foreach ($copiables as $c) { ?><option value="<?php echo (int)$c['codproducto']; ?>"><?php echo e($c['descripcion']); ?></option><?php } ?>
            </select>
            <button class="btn btn-outline-primary"><i class="fas fa-copy"></i> Copiar opciones</button>
        </form>
    <?php } ?>
</div>

<?php foreach ($grupos as $g) {
    $gid = (int)$g['id'];
    $tipo = tipo_grupo($g); ?>
    <div class="card mb-3">
        <div class="card-header bg-white">
            <form method="post" class="form-row align-items-center">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="accion" value="grupo_guardar">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="id_grupo" value="<?php echo $gid; ?>">
                <div class="col-md-4 mb-1"><input type="text" name="nombre" class="form-control font-weight-bold" maxlength="60" value="<?php echo e($g['nombre']); ?>" aria-label="Nombre del grupo" required></div>
                <div class="col-md-3 mb-1">
                    <select name="tipo" class="form-control" aria-label="Tipo de grupo">
                        <?php foreach (TIPOS_GRUPO as $k => [$txt]) { ?><option value="<?php echo $k; ?>" <?php echo $k === $tipo ? 'selected' : ''; ?>><?php echo $txt; ?></option><?php } ?>
                    </select>
                </div>
                <div class="col-md-2 col-6 mb-1">
                    <?php if ($tipo === 'varios') { ?>
                        <input type="number" name="maximo" class="form-control" min="2" max="20" value="<?php echo max(2, (int)$g['maximo']); ?>" title="Máximo a elegir" aria-label="Máximo a elegir">
                    <?php } else { ?>
                        <input type="hidden" name="maximo" value="5"><span class="text-muted small">Máx. 1</span>
                    <?php } ?>
                </div>
                <div class="col-md-1 col-6 mb-1"><input type="number" name="orden" class="form-control" value="<?php echo (int)$g['orden']; ?>" title="Orden" aria-label="Orden"></div>
                <div class="col-md-2 mb-1 text-nowrap">
                    <button class="btn btn-success btn-sm" title="Guardar grupo"><i class="fas fa-save"></i></button>
                    <button class="btn btn-outline-danger btn-sm" form="borrar<?php echo $gid; ?>" title="Eliminar grupo"><i class="fas fa-trash-alt"></i></button>
                </div>
            </form>
            <form method="post" id="borrar<?php echo $gid; ?>" class="confirmar d-none" data-titulo="¿Eliminar el grupo <?php echo e($g['nombre']); ?>?" data-texto="Se borran también sus opciones." data-boton="Sí, eliminar">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="accion" value="grupo_eliminar">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="id_grupo" value="<?php echo $gid; ?>">
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead class="thead-light"><tr><th>Opción</th><th style="width:130px">+ Precio (S/)</th><th>Descuenta stock de</th><th style="width:110px"></th></tr></thead>
                <tbody>
                    <?php foreach ($opcionesPorGrupo[$gid] ?? [] as $o) {
                        $f = 'op' . (int)$o['id']; ?>
                        <tr>
                            <td><input type="text" name="nombre" form="<?php echo $f; ?>" class="form-control form-control-sm" maxlength="60" value="<?php echo e($o['nombre']); ?>" aria-label="Nombre" required></td>
                            <td><input type="number" name="precio" form="<?php echo $f; ?>" class="form-control form-control-sm" step="0.10" min="0" value="<?php echo e($o['precio']); ?>" aria-label="Precio extra"></td>
                            <td>
                                <?php echo select_vinculo($vinculables, $o['id_producto_stock'], $f); ?>
                                <?php if ($o['id_producto_stock'] !== null && (int)$o['controla_stock'] === 1) { ?>
                                    <small class="<?php echo (int)$o['existencia'] <= 0 ? 'text-danger font-weight-bold' : 'text-muted'; ?>">Stock: <?php echo (int)$o['existencia']; ?></small>
                                <?php } ?>
                            </td>
                            <td class="text-nowrap">
                                <form method="post" id="<?php echo $f; ?>" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="accion" value="opcion_guardar">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="id_grupo" value="<?php echo $gid; ?>">
                                    <input type="hidden" name="id_opcion" value="<?php echo (int)$o['id']; ?>">
                                    <button class="btn btn-success btn-sm" title="Guardar"><i class="fas fa-save"></i></button>
                                </form>
                                <form method="post" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="accion" value="opcion_eliminar">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="id_opcion" value="<?php echo (int)$o['id']; ?>">
                                    <button class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="fas fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php $fn = 'nueva' . $gid; ?>
                    <tr class="table-light">
                        <td><input type="text" name="nombre" form="<?php echo $fn; ?>" class="form-control form-control-sm" maxlength="60" placeholder="Nueva opción (ej: Pura papa)" aria-label="Nueva opción" required></td>
                        <td><input type="number" name="precio" form="<?php echo $fn; ?>" class="form-control form-control-sm" step="0.10" min="0" value="0" aria-label="Precio extra"></td>
                        <td><?php echo select_vinculo($vinculables, null, $fn); ?></td>
                        <td>
                            <form method="post" id="<?php echo $fn; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="accion" value="opcion_guardar">
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <input type="hidden" name="id_grupo" value="<?php echo $gid; ?>">
                                <button class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Agregar</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php if ($categorias) { ?>
                <form method="post" class="form-inline p-2 border-top">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="accion" value="agregar_categoria">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="hidden" name="id_grupo" value="<?php echo $gid; ?>">
                    <span class="mr-2 small text-muted">Agregar de una vez todos los productos de</span>
                    <select name="id_categoria" class="form-control form-control-sm mr-2" aria-label="Categoría">
                        <?php foreach ($categorias as $c) { ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$c['id'] === $catBebidas ? 'selected' : ''; ?>><?php echo e($c['nombre']); ?></option><?php } ?>
                    </select>
                    <span class="mr-2 small text-muted">con + S/</span>
                    <input type="number" name="precio" class="form-control form-control-sm mr-2" step="0.10" min="0" value="0" style="width:90px" aria-label="Precio extra">
                    <button class="btn btn-outline-dark btn-sm"><i class="fas fa-layer-group"></i> Agregar</button>
                </form>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<div class="card">
    <div class="card-header bg-primary text-white"><i class="fas fa-plus"></i> Nuevo grupo de opciones</div>
    <div class="card-body">
        <form method="post" class="form-row align-items-end">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="grupo_guardar">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="col-md-4 mb-2">
                <label for="g_nombre">Nombre</label>
                <input type="text" name="nombre" id="g_nombre" class="form-control" maxlength="60" placeholder="Ej: Tipo, Extras, Bebida del combo" required>
            </div>
            <div class="col-md-4 mb-2">
                <label for="g_tipo">El cliente...</label>
                <select name="tipo" id="g_tipo" class="form-control">
                    <option value="obligatorio">debe elegir 1 (ej: Tipo, Bebida del combo)</option>
                    <option value="opcional">puede elegir 1 o ninguna</option>
                    <option value="varios">puede elegir varias (ej: Extras)</option>
                </select>
            </div>
            <div class="col-md-2 col-6 mb-2">
                <label for="g_max">Máximo</label>
                <input type="number" name="maximo" id="g_max" class="form-control" min="2" max="20" value="5">
            </div>
            <div class="col-md-2 col-6 mb-2"><button class="btn btn-primary btn-block">Crear grupo</button></div>
        </form>
        <small class="text-muted">El <b>máximo</b> solo se usa cuando el cliente puede elegir varias. Para descontar una bebida del inventario, vincúlala a su producto en "Descuenta stock de".</small>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
