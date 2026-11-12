# Snack Mary

Sistema de ventas para el snack: mostrador, **mesas**, **pedidos**, **delivery** y **WhatsApp**.
PHP + MySQL/MariaDB, sin frameworks (funciona en hosting gratuito como InfinityFree).

## Funciones

Pensado para usarse en una **tablet** (botones grandes, pantalla completa, instalable como app).

| Módulo | Qué hace |
|---|---|
| **Nueva venta** | Grilla táctil de productos con foto, filtrada por categoría, y lector de código de barras (código + Enter). Mostrador (cobrar al momento o dejar para llevar), mesa o delivery. |
| **Mesas** | Mesas libres/ocupadas. Si una mesa pide más, se suma a la misma cuenta como una **ronda** nueva. |
| **Pedidos** | Pedidos activos con estados, repartidor, mapa, comanda, ticket, WhatsApp, cobro y anulación. Se actualiza solo y **suena** cuando entra un pedido nuevo. |
| **Cocina** | Pantalla oscura solo con lo que hay que preparar, sin precios. En las rondas nuevas marca lo **NUEVO**; los pedidos atrasados (15 min o más) se ponen en rojo. Botones grandes: Preparar → ¡Listo! |
| **Comanda** | Ticket para cocina con letra grande y sin precios (solo la última ronda). |
| **Mis entregas** | Vista de celular para el repartidor: mapa, llamar, WhatsApp, "Salí a entregar" y "Entregado y cobrado". |
| **Caja** | Apertura con sencillo, gastos e ingresos, arqueo por método de pago y cierre con diferencia (sobra/falta). Historial imprimible. |
| **Reportes** | Ventas por día y por hora, por categoría, productos más vendidos, por trabajador, tipo, método y origen. Exporta a Excel. |
| **Opciones por plato** | Cada plato puede tener grupos de opciones: **Tipo** (clásica, pura papa, con mollejitas), **Extras** (+ porción de salchicha) o **Bebida del combo**. Cada opción puede sumar precio y descontar el stock de una bebida. Se eligen en caja y en la carta, y se ven en cocina, comanda, ticket y WhatsApp. Se pueden copiar de un plato a otro. |
| **Productos / Categorías** | Foto (se reduce y recomprime sola), categoría, y productos **sin control de stock** para platos preparados. Las categorías marcadas "no va a cocina" (bebidas) no aparecen en Cocina. |
| **Carta pública** (`carta.php`) | El cliente elige, pone sus datos (o comparte su ubicación GPS) y el pedido **entra directo al sistema**. Luego puede avisar por WhatsApp. Tiene protección anti-spam. |
| **Ventas** | Historial por fechas, ticket PDF, WhatsApp y anulación. |
| **Configuración** | Datos del negocio, WhatsApp, costo de delivery y activar/pausar la carta. |
| **Impresora** | Tickets y comandas en impresoras térmicas **Bluetooth** de 58/80 mm: con la app **RawBT** (Android, cualquier impresora), **Bluetooth directo** desde Chrome (impresoras BLE) o PDF. Impresión automática al vender. Se configura en cada tablet. |
| **Ayuda** | Guías de uso dentro de la app, ordenadas según el puesto de quien entra (cajero, cocina, repartidor, admin), con buscador. |
| **Auditoría** | Solo admin: quién ingresó, falló la clave, anuló, cambió precios o permisos, abrió o cerró caja. |

### Permisos por rol

| Rol típico | Permisos |
|---|---|
| Cajero | Nueva venta, Mesas, Pedidos, Caja, Clientes |
| Cocina | Cocina |
| Repartidor | Repartidor |
| Administrador | Todo (el usuario `admin` siempre tiene todo) |

### Instalar en la tablet como app

Abre `https://snackmary.moqueguasoft.com` en Chrome → menú ⋮ → **Instalar aplicación** (o "Agregar a pantalla principal"). Se abre a pantalla completa, sin barra del navegador.

## Despliegue en VPS (Docker + Traefik)

Se levantan dos contenedores: `snackmary-app` (PHP 8.3 + Apache) y `snackmary-db` (MariaDB 11, con volumen propio).
Traefik publica el sitio en `https://snackmary.moqueguasoft.com` con certificado SSL automático.

1. **DNS:** registro `A` → nombre `snackmary` → `76.13.234.161` → TTL `300`.
2. **Revisa cómo está tu Traefik** (red, entrypoint y certresolver):
   ```bash
   docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}'
   docker network ls
   docker inspect NOMBRE_DE_OTRA_WEB --format '{{range $k,$v := .Config.Labels}}{{$k}}={{$v}}{{"\n"}}{{end}}' | grep traefik
   ```
3. **Clona y configura:**
   ```bash
   cd /opt && git clone https://github.com/andreTYS/snackmary.git && cd snackmary
   cp .env.example .env && nano .env
   ```
4. **Levanta:** `docker compose up -d --build`

La primera vez la base se crea sola con `database/instalacion.sql` (usuario `admin` / `admin`).
Para traer los datos de InfinityFree en su lugar, mira la sección siguiente.

### Pasar los datos de InfinityFree al VPS

```bash
# 1) Exporta la base desde phpMyAdmin de InfinityFree y súbela al VPS como /opt/snackmary/respaldo.sql
# 2) Reemplaza la base nueva por tus datos y aplica la migración
cd /opt/snackmary && set -a && . ./.env && set +a
docker exec -i snackmary-db mariadb -uroot -p"$DB_ROOT_PASS" -e "DROP DATABASE $DB_NAME; CREATE DATABASE $DB_NAME; GRANT ALL ON $DB_NAME.* TO '$DB_USER'@'%';"
docker exec -i snackmary-db mariadb -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < respaldo.sql
docker exec -i snackmary-db mariadb -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < database/migracion_v2.sql
docker exec -i snackmary-db mariadb -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < database/migracion_v3.sql
docker exec -i snackmary-db mariadb -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < database/migracion_v4.sql
docker exec -i snackmary-db mariadb -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < database/migracion_v5.sql
```

### Actualizar a una nueva versión

```bash
cd /opt/snackmary && git pull && docker compose up -d --build
```

Si la versión trae cambios en la base de datos (archivo `database/migracion_vN.sql` nuevo), aplícalo una vez:

```bash
cd /opt/snackmary && set -a && . ./.env && set +a
docker exec -i snackmary-db mariadb -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < database/migracion_v5.sql
```

Cada archivo de migración se aplica una sola vez y en orden (v2 → v3 → v4 → v5); si ya aplicaste uno, aplicarlo de nuevo no rompe nada.

### Copia de seguridad

```bash
cd /opt/snackmary && set -a && . ./.env && set +a
docker exec snackmary-db mariadb-dump -uroot -p"$DB_ROOT_PASS" "$DB_NAME" > respaldo_$(date +%F).sql
```

Las fotos de productos están en el volumen `snackmary_uploads`.

### Instancia de demostración (para mostrar el sistema a clientes)

Una segunda copia del sistema, con **datos de prueba que se reinician cada noche**, en su propio dominio y con su propia base de datos (no toca los datos reales).

1. **DNS:** registro `A` → nombre `snackmary-demo` → `76.13.234.161` → TTL `300`.
2. **Configura y levanta** (en la misma carpeta del sistema):
   ```bash
   cd /opt/snackmary && cp .env.demo.example .env.demo && nano .env.demo
   docker compose --env-file .env.demo -p snackmary-demo up -d --build
   ```
3. **Carga los datos de prueba** (la primera vez, y cuando quieras reiniciar la demo):
   ```bash
   docker exec snackmary-demo-app php /var/www/html/database/demo_datos.php
   ```
4. **Reinicio automático cada noche a las 4:10 a. m.:**
   ```bash
   (crontab -l 2>/dev/null; echo '10 4 * * * docker exec snackmary-demo-app php /var/www/html/database/demo_datos.php >/dev/null 2>&1') | crontab -
   ```

En la demo, la pantalla de ingreso muestra los usuarios de prueba (se entra con un toque) y no se pueden cambiar contraseñas ni desactivar esos usuarios:

| Usuario | Contraseña | Puesto |
|---|---|---|
| `admin` | `Demo2026` | Administrador |
| `cajero` | `Cajero2026` | Cajero / mozo |
| `cocina` | `Cocina2026` | Cocina |
| `moto` | `Moto2026` | Repartidor |

`demo_datos.php` **borra toda la base** antes de cargar los datos: solo funciona con `APP_DEMO=1` (o con `--forzar`). Nunca lo ejecutes en la instancia real.

## Instalación en hosting compartido

1. Sube todos los archivos a `htdocs`.
2. Copia `config.local.example.php` como **`config.local.php`** y pon los datos de tu base de datos.
   Este archivo **no** se sube al repositorio (está en `.gitignore`).
3. Base de datos (en phpMyAdmin):
   - **Si ya tienes el sistema funcionando:** haz una copia de seguridad (Exportar) y luego ejecuta
     `database/migracion_v2.sql`, `migracion_v3.sql`, `migracion_v4.sql` y `migracion_v5.sql` (en ese orden) en la pestaña SQL. Tus ventas, productos y clientes se conservan.
   - **Si es una instalación nueva:** importa `database/instalacion.sql` en una base vacía.
     Usuario inicial: `admin` / `admin`. Cámbiala apenas ingreses.
4. Entra a **Configuración** y escribe el número de WhatsApp del negocio.
5. En **Usuarios**, dale permisos a cada trabajador con el botón de la llave
   (incluye los nuevos permisos *Mesas* y *Pedidos / Delivery*).

Las contraseñas antiguas (MD5) se convierten solas a un formato seguro la primera vez que cada usuario inicia sesión.

## Seguridad

- Credenciales fuera del código (`config.local.php` o variables de entorno).
- Todas las consultas usan sentencias preparadas: no hay inyección SQL.
- Contraseñas con `password_hash` (bcrypt), mínimo 8 caracteres; la de fábrica o una muy común **obliga a cambiarla** al ingresar.
- Límite de intentos en la base de datos: 5 fallos por usuario (o 20 por IP) en 15 minutos bloquean el ingreso. La IP real se toma de Traefik sin confiar en lo que envía el cliente.
- Sesión con cookie `Secure` + `HttpOnly` + `SameSite`, cierre por inactividad (12 h), y un usuario desactivado pierde su sesión al instante.
- Cabeceras de seguridad en Apache: Content-Security-Policy (solo recursos propios), X-Frame-Options, nosniff, HSTS, Referrer-Policy, Permissions-Policy.
- El contenedor deja el código **de solo lectura** para Apache; solo `uploads/` es escribible y ahí nunca se ejecuta PHP.
- Librerías JavaScript actualizadas: jQuery 3.7.1, jQuery UI 1.13.3, DataTables 1.13.11 y Bootstrap 4.6.2. Se corrigen varias vulnerabilidades XSS conocidas (CVE-2021-41182/41183/41184, CVE-2022-31160, CVE-2020-28458, CVE-2021-23445) y se quita un jQuery 1.12.4 que el servidor publicaba sin usarse.
- Registro de **auditoría** de acciones sensibles.
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
