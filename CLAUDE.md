# Backend — SaaS Médico Multi-tenant

API REST en Laravel 13 (PHP 8.3) que sirve de backend a un sistema de gestión para clínicas/consultorios médicos. El frontend vive en un repo separado: `/Users/howard/Desktop/proyecto 1/sakai-vue` (Vue 3 + PrimeVue, template Sakai).

## Base de datos de desarrollo

**Postgres tanto en desarrollo como en producción, a propósito** (decisión explícita de Howard): evita discrepancias de tipos/sintaxis entre lo que se prueba localmente y lo que corre en producción. Se pasó primero por SQLite y luego por MySQL antes de asentarse acá — ver `AUDITORIA.md` para el historial.

Corre en un contenedor Docker local (no instalado a nivel de sistema): `docker run -d --name proyecto1_postgres -e POSTGRES_DB=inv_cv_laravel -e POSTGRES_USER=laravel -e POSTGRES_PASSWORD=laravel_dev_password -p 5432:5432 postgres:16`. Las credenciales ya están en `.env` (`DB_CONNECTION=pgsql`, host `127.0.0.1:5432`, db `inv_cv_laravel`, user `laravel`). Si el contenedor no está corriendo (`docker ps`), levantar Docker Desktop (`open -a Docker`) y luego `docker start proyecto1_postgres` (el contenedor ya existe, no hace falta el `docker run` de nuevo salvo que se haya borrado).

**Tests contra Postgres también** (`phpunit.xml`), no contra SQLite `:memory:` — mismo motivo: para que un test no pase en la suite y falle en real por una diferencia de comportamiento entre motores. Usan una base separada del mismo contenedor, `inv_cv_laravel_test` (creada con `CREATE DATABASE inv_cv_laravel_test`), para que `RefreshDatabase` no toque los datos de desarrollo.

Nota de Postgres específica: las columnas `id` usan secuencias, no `AUTO_INCREMENT` — si alguna vez se insertan filas con `id` explícito por fuera de Eloquent (ej. un script de migración de datos), hay que resincronizar la secuencia después con `SELECT setval(pg_get_serial_sequence('tabla', 'id'), (SELECT MAX(id) FROM tabla))`, o el próximo `Model::create()` sin id explícito choca con una fila ya existente.

`database/database.sqlite` y el contenedor `proyecto1_mysql` (detenido, no borrado) se conservan como backup de los datos previos a esta migración, pero ninguno de los dos es la base activa.

## Modelo de negocio (multi-tenant)

Cada **médico/administrador** (`role = admin`) es un tenant. Los datos de cada tenant se aíslan por `admin_id`:

- `superadmin`: rol global, ve/gestiona todo.
- `admin`: el médico dueño del tenant. Tiene sus propias secretarias y pacientes.
- `secretaria`: pertenece a un `admin` (`admin_id` en `users`). Su alcance real es acotado: **solo ve y crea citas** de ese médico (no las edita/reprograma/marca como atendidas ni las elimina — `CitaPolicy::update()`/`delete()` la excluyen), y **no** accede a Pacientes como pantalla propia ni a historial médico/recetas. La única forma en que toca datos de Pacientes es de forma incidental, dentro del propio flujo de "Nueva cita" (buscar uno existente o crear uno nuevo ahí mismo sin pasar por la gestión de Pacientes) — por eso `PatientPolicy::create()`/`viewAny()` siguen permitiéndole crear/listar pacientes, pero `update()` no.

La autenticación usa **Laravel Sanctum** (tokens, no sesiones SPA cookie). Los roles se aplican vía el middleware `App\Http\Middleware\RoleMiddleware` (alias `role:superadmin,admin`, etc.) sobre grupos de rutas en `routes/api.php`.

## Modelos principales (`app/Models`)

- `User` — incluye soft deletes, cedula, `role`, `admin_id` (tenant al que pertenece si es secretaria), `status`, `blocked`, `login_attempts`, `created_by`. Relaciones: `admin()`, `secretaries()`, `patients()`. Helpers: `isSuperAdmin()`, `isDoctor()`, `isSecretary()`.
- `Patient` — pacientes, con soft deletes y campo pasaporte.
- `Cita` — citas médicas.
- `HistorialMedico` — historial clínico del paciente (incluye campo dieta). Solo accesible por `superadmin`/`admin`.
- `Receta` — recetas médicas: `fecha_emision`, `ars` (editable por documento, igual razón que en `AutorizacionProcedimiento`), `medicamentos`, `indicaciones`. La "Hora" que se imprime no es un campo propio: se toma de `created_at` (se fija sola al crear el registro). Solo accesible por `superadmin`/`admin`.
- `Dieta` — plan de dieta por paciente: solo `fecha` y `dieta` (texto). **No** se relaciona con una consulta (a diferencia de `Receta`, no tiene `historial_medico_id` — se agregó y se quitó a propósito: el diagnóstico de la consulta no pertenece a este documento). Antes vivía como columna `dieta` dentro de `historial_medico` (esa columna sigue ahí, sin usarse, no se migró); ahora es un documento independiente con su propio CRUD, mismo patrón que `Receta`/`AutorizacionProcedimiento`/`LicenciaMedica`, ruta `apiResource('dietas', ...)`.
- `AutorizacionProcedimiento` — documento de autorización de procedimientos por paciente: `fecha`, `ars` (editable por documento, no fijo — puede diferir del `insurance` de la ficha del paciente), `historia_enfermedad`, `estudios_realizados`, `tiempo_evolucion`, `tratamiento_previo`, `diagnostico_presuntivo` (este último incluye los procedimientos propuestos como texto libre). Mismo patrón exacto que `HistorialMedico`/`Receta` (admin_id derivado del paciente, soft delete, solo `superadmin`/`admin`, ruta `apiResource('autorizaciones', ...)`). Se imprime desde el frontend con el membrete del branding del médico (ver abajo).
- `LicenciaMedica` (tabla `licencias_medicas`) — certificado de reposo/licencia por paciente: `fecha`, `constatado` ("Y constatado"), `recomendacion` ("Por lo que recomiendo"), `certificacion_cierre` (línea final "Expido la presente certificación en [ciudad] a partir de hoy día...", el médico la escribe libremente en cada documento). El párrafo de apertura ("Yo: [médico] ... CERTIFICO, haber examinado a:") **no** vive aquí — es `users.licencia_declaracion` en el branding (ver abajo); el nombre y cédula del paciente se insertan automáticamente al imprimir. Mismo patrón que `HistorialMedico`/`Receta`/`AutorizacionProcedimiento`, ruta `apiResource('licencias', ...)`.
- `role.php` / `permission.php` — existen tablas `roles`/`permissions`/`role_user` en migraciones pero el control de acceso real en uso hoy es el campo simple `users.role` + `RoleMiddleware`, no un sistema de permisos granular todavía.

## Branding por tenant

Cada médico (`role = admin`) tiene, en la tabla `users`: `brand_name` (nombre, título del membrete), `brand_color` (hex, color principal — aplicado como color primario de PrimeVue), `brand_color_secondary` (hex, color secundario heredado — ya no tiene forma de asignarse desde la app, ver abajo; se sigue leyendo y aplicando como variable CSS `--brand-secondary` para quien ya lo tuviera cargado), `logo_path` (logo del topbar), `header_icon_left_path`/`header_icon_right_path` (los dos íconos en los extremos del encabezado de los documentos impresos, ej. ilustración y sello del colegio médico), `header_credentials` (párrafo libre con especialidad/teléfonos/email/dirección, va debajo del nombre en el centro del encabezado) y `licencia_declaracion` (párrafo libre de apertura de la Licencia Médica: nombre del médico, exequátur, cédula — la app le concatena el nombre/cédula del paciente). `User::tenant()` resuelve de quién es el branding que debe verse: el propio si es `admin`, el de su médico (`admin_id`) si es `secretaria`, `null` si es `superadmin`. `BrandingController` expone:
- `GET /api/v1/branding` — cualquier autenticado consulta su branding efectivo (solo lectura, para pintar el topbar y el membrete de documentos).
- `PUT /api/v1/branding/color` (middleware `role:admin`) — el propio médico fija su `brand_color`. Es la única vía para asignarlo: la llama el frontend (`AppConfigurator.vue`) cada vez que el médico elige un swatch "Primary" en la paleta de colores de la app (arriba a la derecha), así queda guardado y se reaplica en sus próximas sesiones/dispositivos vía `useBranding.js`.
- `GET/PUT /api/v1/users/{user}/branding`, `POST /api/v1/users/{user}/branding/logo`, `POST /api/v1/users/{user}/branding/header-icon-left` y `.../header-icon-right` — **solo el Superadmin** (middleware `role:superadmin`), gestionando nombre/credenciales/logo/íconos de un médico puntual por su id desde la pantalla de Usuarios. El color quedó fuera de esta vía a propósito (antes el Superadmin lo asignaba por el médico; ahora solo el propio médico lo elige, ver punto anterior) — `updateForUser` ya no acepta `brand_color`/`brand_color_secondary`.

Todas las imágenes de branding se guardan en el disco `public` (`storage/app/public/branding` para íconos/sello, `storage/app/public/logos` para el logo; requiere `php artisan storage:link`). `APP_URL` en `.env` debe coincidir con el host:puerto real donde corre el backend (ej. `http://127.0.0.1:8000`), o las URLs de estas imágenes quedan mal armadas y no cargan en el navegador.

## Rutas (`routes/api.php`)

- `POST /api/v1/auth/register`, `POST /api/v1/auth/login` — públicas.
- `GET /api/v1/auth/profile`, `POST /api/v1/auth/logout` — protegidas por `auth:sanctum`.
- `GET/POST/PUT /api/v1/users...` — solo `superadmin,admin`. Incluye `PUT /api/v1/users/{id}/restore` (`UserController::restore()`) para reactivar (deshacer soft delete + `status=true`) un usuario eliminado encontrado antes con `users/eliminados/buscar`; mismo criterio de permiso que `destroy()` (`UserPolicy::owns()`, ver abajo).
- `apiResource('patients', ...)`, `apiResource('citas', ...)` — accesibles a médico, secretaria y superadmin, pero con permisos distintos por verbo dentro de cada uno (ver `PatientPolicy`/`CitaPolicy`): la secretaria solo tiene `create`/`view`/`viewAny`, nunca `update`/`delete`.
- `apiResource('historial', ...)`, `apiResource('recetas', ...)`, `apiResource('dietas', ...)`, `apiResource('autorizaciones', ...)`, `apiResource('licencias', ...)` — solo `superadmin,admin` (secretaria excluida).

## Convenciones observadas

- Español para nombres de dominio (Cita, Receta, HistorialMedico, Pacientes) y para mensajes de commit; inglés para el resto del código Laravel estándar.
- Migraciones van agregando columnas incrementalmente sobre `users`/`patients` (varios `add_*_to_*_table`) en vez de rehacer la tabla — seguir ese patrón al modificar el esquema.
- `Route::apiResource('autorizaciones', ...)` necesita `->parameters(['autorizaciones' => 'autorizacion'])`: Laravel singulariza mal "autorizaciones" por defecto (da "autorizacione"), lo que rompe el route-model-binding contra el parámetro `$autorizacion` del controlador — el modelo nunca se resolvía desde la BD y la policy rechazaba la petición como si fuera un registro vacío (403 falso al editar/eliminar). Revisar `php artisan route:list` si se agrega otro recurso con un nombre plural poco común en español.
- Hay una env var `SUPERADMIN_CAN_DELETE` que sugiere un flag de negocio para permitir o no borrado físico/lógico por el superadmin — revisar su uso antes de tocar borrado de usuarios.
- Modelos con nombre compuesto en español (`HistorialMedico`, `LicenciaMedica`, `EstudioMedico`) necesitan `protected $table = '...'` explícito: Eloquent adivina el nombre de tabla pluralizando solo la última palabra del nombre de la clase (ej. `EstudioMedico` → `estudio_medicos`), pero las migraciones de este proyecto usan el plural natural en español con la primera palabra pluralizada (`estudios_medicos`, `licencias_medicas`). Sin el `$table` explícito, Eloquent apunta a una tabla que no existe ("no such table") en cuanto se intenta el primer insert/select.
- En `User`, `admin_id` (no `created_by`) es la relación real de pertenencia de una secretaria a su médico (`User::secretaries()` es `hasMany(User::class, 'admin_id')`). `created_by` solo registra quién ejecutó el alta y puede ser el superadmin cuando crea una secretaria en nombre de un médico — en ese caso ambos campos difieren. Cualquier query/policy que deba responder "¿esta secretaria es mía?" (listados, búsqueda de eliminados, `UserPolicy::owns()`) tiene que filtrar por `admin_id`; usar `created_by` ahí deja fuera a las secretarias que no creó el propio médico (síntoma típico: el médico no encuentra/edita a una secretaria suya que sí existe).

## Preferencias del usuario (Howard)

- Trabajar de forma **autónoma** en los cambios de código (no pedir confirmación por cada edición).
- **Siempre preguntar antes de hacer `git commit`.**
