# Snack Mary

Sistema de ventas para el snack: mostrador, **mesas**, **pedidos**, **delivery** y **WhatsApp**.
PHP + MySQL/MariaDB, sin frameworks (funciona en hosting gratuito como InfinityFree).

## Funciones

| Módulo | Qué hace |
|---|---|
| **Nueva venta** | Venta en mostrador, pedido en mesa o delivery. Busca clientes y productos, controla stock, método de pago (efectivo, Yape, Plin, tarjeta, transferencia) y nota para cocina. |
| **Mesas** | Mesas libres/ocupadas. Una mesa ocupada acumula los productos en la misma cuenta hasta que se cobra. |
| **Pedidos / Delivery** | Pedidos activos con estados (pendiente → preparando → listo / en camino → entregado → pagado). Se actualiza solo cada 30 s. Botón para cobrar, anular (devuelve stock), imprimir ticket y enviar el pedido por WhatsApp al cliente. |
| **Carta pública** (`carta.php`) | Página para clientes: arman su pedido y lo envían por WhatsApp al número del negocio. No necesita login. |
| **Ventas** | Historial filtrado por fechas, total cobrado por método de pago, ticket PDF, WhatsApp y anulación. |
| **Clientes / Productos / Usuarios** | Registro, edición, desactivar y reactivar. Permisos por usuario. |
| **Configuración** | Datos del negocio, número de WhatsApp y código de país. |

## Instalación

1. Sube todos los archivos a `htdocs`.
2. Copia `config.local.example.php` como **`config.local.php`** y pon los datos de tu base de datos.
   Este archivo **no** se sube al repositorio (está en `.gitignore`).
3. Base de datos (en phpMyAdmin):
   - **Si ya tienes el sistema funcionando:** haz una copia de seguridad (Exportar) y luego ejecuta
     `database/migracion_v2.sql` en la pestaña SQL. Tus ventas, productos y clientes se conservan.
   - **Si es una instalación nueva:** importa `database/instalacion.sql` en una base vacía.
     Usuario inicial: `admin` / `admin`. Cámbiala apenas ingreses.
4. Entra a **Configuración** y escribe el número de WhatsApp del negocio.
5. En **Usuarios**, dale permisos a cada trabajador con el botón de la llave
   (incluye los nuevos permisos *Mesas* y *Pedidos / Delivery*).

Las contraseñas antiguas (MD5) se convierten solas a un formato seguro la primera vez que cada usuario inicia sesión.

## Seguridad (cambios de la versión 2)

- Credenciales fuera del código (`config.local.php`).
- Todas las consultas usan sentencias preparadas: no hay inyección SQL.
- Contraseñas con `password_hash` (bcrypt). Login con límite de intentos.
- Permisos que realmente bloquean (antes la página seguía ejecutándose tras la redirección).
- Token CSRF en todos los formularios y acciones AJAX; las eliminaciones son solo por POST.
- Todo el texto se escapa al mostrarse (sin XSS).
- `ajax.php`, `chart.php` y los tickets PDF exigen sesión iniciada.
- `.htaccess` bloquea el acceso a `config.local.php`, `conexion.php`, `/database` y los `.sql`.

## Errores corregidos

- El permiso de *Configuración* no funcionaba (tenía tilde en la base de datos).
- La búsqueda de productos mostraba productos desactivados.
- El total del carrito se calculaba mal al agregar el mismo producto varias veces.
- Se podía vender sin stock (stock negativo); ahora se valida dentro de una transacción.
- El id de la venta se obtenía con `MAX(id)` (fallaba con dos cajeros a la vez).
- Se podía generar una venta con el carrito vacío.
- La fecha de la venta cambiaba al editarla (`ON UPDATE`).
- El precio lo enviaba el navegador; ahora se toma de la base de datos.
- No se podían quitar todos los permisos de un usuario.
