> Actualización vigente MANUEL-02: seis cargos, cuatro cuentas locales y permisos individuales;168 pruebas/1,019 aserciones. Consulte roles_usuarios/LEEME.md. Los datos y resultados inferiores corresponden a la entrega inicial.

# Evidencias de la entrega de Manuel

Referencia local: MANUEL-01. Fecha: 19 de octubre de 2026, `America/Guatemala`. Estas evidencias corresponden a acceso, configuración y catálogos. No hay un identificador Jira real ni hash de commit documentado en este registro.

## Archivos conservados

| Archivo | Qué acredita | Límites |
| --- | --- | --- |
| [phpunit.txt](phpunit.txt) | PHPUnit 12.5.40 con PHP 8.4.12: **120 pruebas, 526 aserciones, 32.976 s, 62 MB; correcto**. | Es salida agregada, sin detalle por caso ni cobertura de código. Las aserciones concretas se consultan en los archivos de tests y la matriz. |
| [concurrencia-administrador.txt](concurrencia-administrador.txt) | Caso M-07 real con dos conexiones a `sicovi_test`, esquema `sicovi`: espera de bloqueo observada, un administrador activo, uno inactivo y una auditoría de desactivación. | Independiente de las 120 pruebas; acredita la carrera administrativa, no concurrencia de inventario o venta. |
| [inicio.png](inicio.png) | Inicio autenticado de la aplicación real con identidad SICOVI y conteos ficticios de catálogos: 5 productos, 2 categorías, 1 subcategoría, 1 marca y 3 unidades. | Captura local de `sicovi_verificacion`. No contiene credenciales; no demuestra por sí sola todas las operaciones de un rol ni el tamaño objetivo 1366 x 768. |
| [movil-360x800.png](movil-360x800.png) | Formulario real de nuevo producto en iframe de **360 x 800 CSS px**, mostrado con escala visual **75%**. El encabezado de comprobación indica contenido y desplazamiento de **345 px**, sin desborde horizontal. | Es comprobación de viewport mediante iframe, no prueba en teléfono físico ni recorrido de todos los formularios. El tamaño de la imagen completa incluye el contenedor de verificación. |

La persistencia de las pruebas de integración utiliza PostgreSQL 17.11. **`sicovi_test` es la base y `sicovi` es el esquema**. El usuario local, claves, tokens, contraseñas y cadenas de conexión no forman parte de esta evidencia.

## Lectura de la concurrencia administrativa

El resumen JSON registra `result=passed`, `distinct_postgresql_connections=2`, `lock_wait_observed=true`, `active_administrators=1`, `inactive_users=1` y `audit_successes=1`. Un worker termina correctamente (`exit_code=0`, `deactivated`); el otro recibe el rechazo esperado (`exit_code=10`, `last_administrator_protected`).

Se utilizó una demora controlada de un segundo para observar contención real del bloqueo. `fixtures_retained=true` describe el estado al finalizar esa corrida: el script conserva los dos usuarios ficticios y la auditoría para inspección. No debe asumirse que una suite posterior dejó el mismo estado de la base.

El ejecutor [run_admin_race.php](../../../tests/Concurrency/run_admin_race.php) exige CLI directa, `APP_ENV=testing`, conexión efectiva de prueba, ausencia de configuración cacheada y tabla de usuarios vacía antes de preparar su fixture. No borra datos para poder repetirlo. Debe leerse su preparación y la guarda antes de una nueva ejecución; nunca se redirige a desarrollo para provocar o demostrar una limpieza.

## Relación con los casos de prueba

La [matriz M-01 a M-20](../../pruebas/MATRIZ_MANUEL.md) identifica requisito, precondiciones, entradas, pasos, esperado, obtenido y evidencia. Los casos automatizados están en:

- [AccessTest](../../../tests/Feature/AccessTest.php): login, sesión, permisos, usuarios, administrador, contraseñas, comando inicial y rollback de acceso.
- [CatalogTest](../../../tests/Feature/CatalogTest.php): productos/catálogos, costos, relaciones, números, saldo cero, auditoría y seeders.
- [BaseDataTest](../../../tests/Feature/BaseDataTest.php): identidad PostgreSQL, restricciones, organización única y conservación de datos al actualizar.
- [DatabaseSafetyTest](../../../tests/Feature/DatabaseSafetyTest.php): rechazos de guarda mediante conexiones simuladas, sin abrir ni limpiar bases.
- [InterfaceTest](../../../tests/Feature/InterfaceTest.php): render de 24 destinos administrativos y rechazo de arrays en formularios/filtros.
- [DecimalServiceTest](../../../tests/Unit/DecimalServiceTest.php): BCMath, half-up, cantidades, límites y formatos exactos.

Las contraseñas literales que aparecen en fixtures son datos de prueba descartables; no corresponden a la cuenta temporal generada para QA en sicovi_verificacion. Las sesiones HTTP de PHPUnit usan almacenamiento `array`; los casos de revocación verifican además filas de `sessions` en PostgreSQL. La visita en navegador aporta evidencia de ejecución local real, con el alcance visual indicado en cada captura.

## Comprobaciones reportadas y pendientes

`composer validate`, comprobación de requisitos de plataforma y build de frontend se reportaron correctos durante la ejecución local. El build usa Vite 7.3.7 y Bootstrap 5.3.8. Las salidas independientes se conservan en composer-validacion.txt, plataforma.txt y frontend-build.txt.

La revisión de escritorio1366 x 768 está conservada en escritorio-1366x768.png: contenido/desplazamiento1351px, sin desborde horizontal. El menú móvil se comprobó expandido (menu-movil.png). Treinta rutas constan en rutas.txt; caché y lint de vistas Blade correctos. El render de las pantallas en InterfaceTest ya está incluido en el resultado de PHPUnit. No se declara revisión exhaustiva de teclado, datos largos o todos los estados visuales a partir de una sola captura.

Permanecen pendientes la instalación y recepción en otra computadora, la restauración cronometrada en una base vacía y el rendimiento comercial global. No hay evidencia de clientes, proveedores, compras, ventas, movimientos, kardex o anulaciones implementados por Manuel. Tampoco se atribuye aquí una aprobación de Yourguen, una restauración realizada, integración de Jira o despliegue de producción.

## Cierre de verificación

Hora local: 2026-10-19 12:17:31 America/Guatemala. Instalación de desarrollo: sicovi_local/sicovi, una empresa/sucursal/bodega, cuatro roles/22 permisos, cinco productos/saldos0, cero usuarios; diagnóstico sanitizado en postgresql-local.json. Cree su propio administrador mediante el comando interactivo. QA usa sicovi_verificacion y una contraseña aleatoria privada. No se comparten credenciales.

El primer comando agrupado de instalación/pruebas fue rechazado por revisión automática; se comprobó que sicovi_local era una base nueva vacía y se separaron instalación y pruebas protegidas. Las acciones posteriores se aprobaron y finalizaron. No queda un bloqueo de autorización pendiente.

Salidas adicionales: composer-validacion.txt (válido), plataforma.txt (requisitos cumplidos), rutas.txt (30 rutas), frontend-build.txt (build correcto), postgresql-local.json (sin secretos), escritorio-1366x768.png y login.png (salida de sesión real). Capturas de QA tomadas en sicovi_verificacion.
