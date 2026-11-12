# La Tiendita — Backend en Laravel 13 (MVC + API REST + Vistas Blade + POS Móvil)

Sistema de gestión comercial, inventario, pedidos en línea y **Terminal Móvil de Punto de Venta (POS) en Tiempo Real con Control Anti-Malversación** en **Bolivianos (`Bs.`)**, desarrollado en **Laravel 13**.

---

## Cómo instalar y arrancar el Backend

```powershell
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
if (!(Test-Path database\database.sqlite)) { New-Item -ItemType File database\database.sqlite }
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

- **Aplicación Web (Blade MVC)**: `http://localhost:8000`
- **API REST JSON**: `http://localhost:8000/api`
- **Pruebas automatizadas (PHPUnit)**: `php artisan test`

---

## Cuentas precargadas (Seeders para Defensa)

| Rol | Correo | Contraseña | Permisos principales |
|---|---|---|---|
| **ADMIN** | `admin@tiendita.local` | `secret1234` | CRUD completo de productos, papelera (`SoftDeletes`), usuarios, anulación auditada de ventas, arqueo de caja por vendedor y exportación CSV |
| **VENDEDOR** | `vendedor@tiendita.local` | `secret1234` | Terminal Móvil POS en tiempo real (tienda física), ajuste de stock, despacho de pedidos en línea y arqueo de su propia caja |
| **COMPRADOR** | `comprador@tiendita.local` | `secret1234` | Catálogo público, creación de pedidos en línea y consulta de su propio historial de compras |

---

## Mapa de la Rúbrica (Segundo Parcial — 100 pts)

| Requisito | Ruta dentro del repositorio |
|---|---|
| **1. Arquitectura MVC y capas (15 pts)** | `routes/web.php`, `routes/api.php`, `app/Http/Controllers/`, `app/Services/TiendaService.php`, `app/Models/`, `database/migrations/` |
| **2. CRUD completo (20 pts)** | `app/Http/Controllers/ProductoController.php` y `app/Http/Controllers/Api/TiendaApiController.php` (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) |
| **3. Autenticación real (15 pts)** | `app/Http/Controllers/AuthController.php` (`/register`, `/login`, `/logout`), contraseñas con `bcrypt` y bloqueo anti-fuerza bruta |
| **4. Middleware y Guards (15 pts)** | `app/Http/Middleware/EnsureRole.php`, `EnsureApiToken.php`, `SecurityHeadersMiddleware.php` y `app/Policies/ProductoPolicy.php` |
| **5. Validación en Servidor (10 pts)** | `app/Http/Requests/ProductoRequest.php`, `RegistroRequest.php`, `LoginRequest.php`, `VentaRequest.php`, `PedidoRequest.php` |
| **6. Variables de entorno (5 pts)** | `.env` (ignorado en `.gitignore`) y plantilla `.env.example` con variables `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| **7. Extras (20 pts)** | **Roles** (`ADMIN`, `VENDEDOR`, `COMPRADOR`), **Panel con métricas** (`/panel`), **Búsqueda y filtros** (`Producto::scopeFiltrar`), **Papelera (`SoftDeletes`)** (`/productos-papelera`), **Exportar CSV** (`/productos-exportar`, `/ventas/exportar`) y **Bitácora/Avisos + POS Móvil Anti-Malversación** (`AuditLogger.php`, `/vendedor/ventas`) |
