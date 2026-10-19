> Actualización vigente MANUEL-02: seis cargos, cuatro cuentas locales y permisos individuales;168 pruebas/1,019 aserciones. Consulte MATRIZ_MANUEL_02.md. Los datos y resultados inferiores corresponden a la entrega inicial.

# Matriz de pruebas de Manuel

Referencia: MANUEL-01, identificador local; no representa una incidencia Jira verificada. Fecha de registro: 19 de octubre de 2026, `America/Guatemala`. No se registra un hash de commit que todavía no existe.

Esta matriz cubre la base, acceso y catálogos de Manuel. Las verificaciones de compras, ventas, última unidad de inventario, idempotencia comercial y anulaciones corresponden a los autores de esos módulos y no se presentan como ejecutadas aquí.

## Ejecución y criterios de lectura

La última salida conservada de PHPUnit es **correcta: 120 pruebas, 526 aserciones, 32.976 segundos, 62 MB**, con PHPUnit 12.5.40 y PHP 8.4.12. Persistencia: PostgreSQL 17.11; **base `sicovi_test`, esquema `sicovi`**. El resultado agregado está en [phpunit.txt](../evidencias/manuel/phpunit.txt). Los casos y variantes se identifican en los archivos de prueba enlazados; esa salida no es un informe individual por cada caso.

La carrera del último administrador se ejecutó además mediante dos procesos y conexiones PostgreSQL distintas. Su salida independiente está en [concurrencia-administrador.txt](../evidencias/manuel/concurrencia-administrador.txt). No forma parte del conteo de las 120 pruebas de PHPUnit.

Estados: **correcto** significa ejecutado con las comprobaciones descritas; **parcial** distingue lo automatizado de verificaciones manuales pendientes; **pendiente** significa que no hay ejecución demostrada. Un resultado correcto de esta fase no completa por sí solo un requisito global de SICOVI.

Precondiciones comunes, reutilizadas para evitar repetir la preparación:

- **P1:** dependencias instaladas, PostgreSQL aislado accesible y esquema `sicovi` preparado en `sicovi_test`. `Tests\DatabaseTestCase` verifica la identidad efectiva antes de `RefreshDatabase`, ejecuta `SystemSeeder` y restaura el aislamiento entre casos. Las pruebas de restricciones SQL utilizan una transacción anidada para recuperar la conexión tras el rechazo esperado.
- **P2:** P1 y usuario de prueba con rol `admin`. Los roles iniciales son `admin`, `purchasing`, `seller` y `viewer`. Las credenciales de fixtures son exclusivas de pruebas; no son cuentas entregadas al usuario ni secretos reales.
- **P3:** P2 y categoría `TEST-CAT`, unidad entera `TEST-UND` y producto de referencia `TEST-PRODUCT`, precio `25.00`, costo `15.00`, mínimo `2`, cuando el caso lo requiere. Los fixtures se crean dentro del aislamiento de prueba.
- **P4:** utilidades puras o conexiones simuladas, sin abrir ni limpiar una base de datos. Aplica a decimales y casos negativos de la guarda.
- **P5:** navegador contra instalación local en `sicovi_verificacion`, con usuario autorizado y datos ficticios. Esta comprobación se hizo en la misma computadora; no equivale a recepción externa.

Las solicitudes HTTP de PHPUnit utilizan sesión `array` para aislar clientes. Las pruebas de revocación comprueban también filas en la tabla PostgreSQL `sessions`. El acceso real mediante navegador complementa la suite; no se atribuye a las pruebas HTTP una comprobación exhaustiva de cookies, CSRF o todos los navegadores.

## Arranque, acceso y seguridad

### M-01. Arranque y esquema — correcto

- **Requisitos:** RNF-02 y RNF-05; infraestructura de RF-01 a RF-03.
- **Precondiciones y entradas:** P1; migraciones base y `SystemSeeder` en PostgreSQL.
- **Pasos:** preparar la suite con la guarda; ejecutar migraciones; consultar driver, `current_database()`, `current_schema()` y tipos temporales; intentar una segunda empresa, sucursal y bodega, y desactivar las únicas sedes operativas mediante SQL.
- **Esperado:** motor `pgsql`, base `sicovi_test`, esquema `sicovi`, restricciones efectivas e instantes con zona horaria; organización única y operativa.
- **Obtenido:** identidad exacta verificada, seis columnas temporales seleccionadas de usuarios/productos/auditoría son `timestamp with time zone`; segunda organización rechazada con SQLSTATE `23505` y sedes inactivas con `23514`.
- **Evidencia:** [BaseDataTest](../../tests/Feature/BaseDataTest.php), [DatabaseTestCase](../../tests/DatabaseTestCase.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-02. Login — correcto

- **Requisitos:** RF-01 y RNF-01.
- **Precondiciones y entradas:** P2; cuenta válida, email con espacios/mayúsculas, cuenta inexistente, usuario o rol inactivos y contraseña inválida.
- **Pasos:** solicitar una ruta protegida como invitado; enviar login válido y variantes inválidas; realizar cinco intentos fallidos y un sexto intento; enviar email como array.
- **Esperado:** redirección al login para invitados, autenticación con email normalizado, mensaje genérico para cuentas no habilitadas, limitación de intentos y validación de tipos sin error 500.
- **Obtenido:** casos correctos; se registra último acceso, el sexto intento devuelve limitación y los arrays se rechazan como validación. El login real y acceso al inicio también se comprobaron en P5.
- **Evidencia:** [AccessTest](../../tests/Feature/AccessTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt), [inicio.png](../evidencias/manuel/inicio.png). La captura corresponde al inicio autenticado; no contiene la contraseña ni sustituye un registro individual del formulario de login.

### M-03. Sesión — correcto en el alcance ejecutado

- **Requisitos:** RF-01 y RNF-01.
- **Precondiciones y entradas:** P2; usuario autenticado, cuenta posteriormente desactivada y rol posteriormente inactivo; filas de sesión de prueba para restablecimiento.
- **Pasos:** cerrar sesión y volver a ruta protegida; desactivar usuario/rol con sesión existente; cambiar contraseña propia con contraseña actual incorrecta y luego correcta; restablecer administrativamente otra cuenta.
- **Esperado:** pérdida de acceso, rechazo de cuentas no habilitadas, comprobación de contraseña actual y revocación de sesiones al cambiar/restablecer contraseña.
- **Obtenido:** logout y bloqueo de cuenta/rol comprobados; cambio válido termina en login y elimina filas de sesión; restablecimiento actualiza el hash, elimina sesiones y no registra la contraseña. El caso no es una prueba de todos los escenarios de cookies o solicitudes simultáneas de sesión.
- **Evidencia:** [AccessTest](../../tests/Feature/AccessTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-04. Permisos — correcto

- **Requisitos:** RF-02 y RNF-01.
- **Precondiciones y entradas:** P3; actores `seller` y `viewer`; solicitudes directas de creación/edición/desactivación de productos y catálogos, usuarios, roles y configuración.
- **Pasos:** enviar POST, PUT y PATCH sin utilizar los botones de la interfaz; comparar registros tras el rechazo; revocar `products.view` y consultar con una instancia de usuario ya existente.
- **Esperado:** HTTP 403, ningún cambio no autorizado y revocación efectiva sin caché de permisos obsoleta.
- **Obtenido:** ambos roles reciben rechazo; productos y catálogos conservan sus atributos y la revocación se aplica a la siguiente consulta.
- **Evidencia:** [AccessTest](../../tests/Feature/AccessTest.php), [CatalogTest](../../tests/Feature/CatalogTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-05. Costos — correcto

- **Requisitos:** RF-03 y RNF-01.
- **Precondiciones y entradas:** P3; costo distintivo `987654.32`, usuarios `seller` y `viewer` sin `products.view_cost`.
- **Pasos:** consultar listado y detalle; buscar valor crudo, valor formateado `987,654.32` y nombre de campo `reference_cost`; inspeccionar serialización del producto.
- **Esperado:** el costo no se entrega en HTML, campos ocultos ni serialización general.
- **Obtenido:** no aparecen los valores ni el nombre del campo en las respuestas examinadas; `toArray()` omite el costo.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-06. Usuarios — correcto

- **Requisitos:** RF-02, RF-12 base y RNF-01.
- **Precondiciones y entradas:** P2; nombre con espacios repetidos, email `NUEVO@example.test` con espacios exteriores y contraseña de fixture válida; editor con solo `users.update`.
- **Pasos:** crear cuenta; comprobar normalización y hash; repetir email equivalente; intentar inyectar `role_id`, `active` y arrays; enviar después una edición de nombre autorizada.
- **Esperado:** nombre/email normalizados, correo único, contraseña con hash y privilegios protegidos; auditoría sin contraseña.
- **Obtenido:** se guarda `Nuevo Usuario` y `nuevo@example.test`; duplicado rechazado sin nueva fila; inyección de rol/estado rechazada y cambio de nombre permitido; tipos incorrectos generan errores de validación.
- **Evidencia:** [AccessTest](../../tests/Feature/AccessTest.php), [BaseDataTest](../../tests/Feature/BaseDataTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-07. Último administrador — correcto, incluida concurrencia real

- **Requisitos:** RF-02 y RNF-01/RNF-02.
- **Precondiciones y entradas:** P2 para variantes secuenciales; ejecución independiente en `sicovi_test/sicovi` con dos administradores y dos conexiones distintas para la carrera.
- **Pasos:** intentar desactivar/degradar al único administrador y quitar `roles.manage` o desactivar su rol; con dos administradores, desactivar uno e intentar desactivar el restante; lanzar dos procesos que intentan desactivarse y observar el bloqueo compartido.
- **Esperado:** conservar un administrador activo con permisos de recuperación; una operación concurrente puede confirmar y la otra debe revertirse, sin dos eventos de éxito.
- **Obtenido:** variantes de PHPUnit correctas. Carrera real: `distinct_postgresql_connections=2`, `lock_wait_observed=true`, `active_administrators=1`, `inactive_users=1`, `audit_successes=1`; salidas de workers `0` y `10` (`deactivated` / `last_administrator_protected`). Se utilizó una demora controlada de un segundo para observar la contención. Los fixtures quedaron conservados al terminar esa ejecución.
- **Evidencia:** [AccessTest](../../tests/Feature/AccessTest.php), [run_admin_race.php](../../tests/Concurrency/run_admin_race.php), [admin_worker.php](../../tests/Concurrency/admin_worker.php), [concurrencia-administrador.txt](../evidencias/manuel/concurrencia-administrador.txt). Esta prueba no demuestra concurrencia comercial sobre existencias.

## Catálogos, productos y precisión

### M-08. Mantenimiento y consultas — correcto en los flujos automatizados

- **Requisitos:** RF-03 y RNF-04.
- **Precondiciones y entradas:** P3; categoría con código `office`, nombre `Artículos   de oficina`, 17 marcas de paginación y producto `sample-01`.
- **Pasos:** crear/consultar/editar/desactivar categoría y producto; filtrar por texto/estado; consultar lista vacía y segunda página; renderizar mantenimientos auxiliares.
- **Esperado:** persistencia real, normalización, cambio de estado explícito, búsqueda y paginación que conserva filtros.
- **Obtenido:** categoría `OFFICE` y producto `SAMPLE-01` guardados; edición de precio a `30.00`; desactivación y reactivación persistidas. Las 17 marcas se reparten 15/2 y conservan `q=paginada&active=1`. Se prueban CRUD representativos del flujo compartido; no se afirma una revisión manual completa de cada formulario.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [InterfaceTest](../../tests/Feature/InterfaceTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-09. Duplicados y códigos — correcto

- **Requisitos:** RF-02/RF-03 y RNF-02.
- **Precondiciones y entradas:** P3; `Marca normalizada`, SKU `UNIQUE-01`, barcode `000111222`, correo y códigos equivalentes con espacios o distinta capitalización.
- **Pasos:** repetir nombres normalizados, SKU/barcode y email; repetir subcategoría dentro de su categoría y en otra; crear dos productos con barcode vacío; intentar eludir formularios con SQL.
- **Esperado:** equivalentes rechazados sin escrituras parciales; subcategoría única dentro de su padre; barcode vacío como NULL y ceros iniciales conservados.
- **Obtenido:** rechazos de validación y SQLSTATE `23505` según la vía; conteos de productos/saldos no cambian ante fallos; barcode `0012345678905` se conserva y dos ausentes coexisten como NULL; mismo nombre de subcategoría permitido en otra categoría.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [BaseDataTest](../../tests/Feature/BaseDataTest.php), [AccessTest](../../tests/Feature/AccessTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-10. Categoría y subcategoría — correcto

- **Requisitos:** RF-03 y RNF-02.
- **Precondiciones y entradas:** P3; subcategoría perteneciente a otra categoría.
- **Pasos:** crear producto con pareja incompatible mediante HTTP; intentar después una actualización SQL directa a esa pareja sobre un producto válido.
- **Esperado:** rechazo en aplicación y FK PostgreSQL, conservando el registro válido.
- **Obtenido:** error de `subcategory_id`, ausencia de producto parcial y SQLSTATE `23503`; la relación original continúa sin subcategoría.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-11. Estados inactivos y relaciones — correcto

- **Requisitos:** RF-03 y RNF-02.
- **Precondiciones y entradas:** P3; categoría, subcategoría, marca o unidad inactiva; catálogos vinculados a producto activo; relación actual inactiva preexistente de prueba.
- **Pasos:** asignar cada catálogo inactivo a producto nuevo; desactivar los catálogos vinculados; editar un producto conservando su relación actual inactiva; intentar cambiar la precisión de unidad referenciada.
- **Esperado:** no permitir asignaciones nuevas ni desactivaciones incompatibles; preservar relaciones actuales legibles y precisión utilizada.
- **Obtenido:** errores asociados al campo; catálogos conservan estado activo; relación preexistente se muestra y no se reasigna; unidad conserva cero decimales ante intento de cambiarla a tres.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt). El bloqueo por historial de movimientos queda reservado a Yourguen.

### M-12. Cantidades y montos — correcto

- **Requisitos:** RF-03 y RNF-02.
- **Precondiciones y entradas:** P3; variantes `-0.01`, `1.001`, `1000000000000.00`, `-1.00`, `1e3`, `NaN`, cantidad `1.5` en unidad entera, `0.0001` y `100000000000.000`.
- **Pasos:** enviar cada variante de precio/costo/mínimo; crear producto con todos esos valores en cero; crear otro con unidad de tres decimales y mínimo `0.333`.
- **Esperado:** rechazar negativos, precisión excesiva, formatos/rangos inválidos e incompatibilidad de unidad; admitir cero donde corresponde y fracciones compatibles.
- **Obtenido:** variantes inválidas generan errores de campo sin productos ni saldos parciales; ceros se guardan `0.00`/`0.000`; mínimo fraccionario conserva `0.333`.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [DecimalServiceTest](../../tests/Unit/DecimalServiceTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-13. Decimales exactos — correcto

- **Requisitos:** RF-03 y RNF-02; utilidad para contratos comerciales futuros.
- **Precondiciones y entradas:** P4 y BCMath; cadenas decimales, límites NUMERIC y variantes inválidas.
- **Pasos:** calcular líneas y sumar importes ya redondeados; validar precisión/unidad/rango; formatear importes grandes; provocar overflow de línea/total y pasar una línea float.
- **Esperado:** cálculo sin float, redondeo half-up y errores explícitos sin truncar datos inválidos.
- **Obtenido:** `2 × 25.00 = 50.00`, `0.333 × 10.00 = 3.33`, `1.005 × 1.00 = 1.01`; dos líneas de `1.005 × 1.00` suman `2.02`. También se verifican `0.001 × 5.00 = 0.01`, formato `Q 99,999,999,999,999.99`, límites y rechazo de notación científica, separadores ambiguos y floats.
- **Evidencia:** [DecimalServiceTest](../../tests/Unit/DecimalServiceTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt). No implica que existan ventas o documentos comerciales.

### M-14. Saldo cero y manipulación de stock — correcto

- **Requisitos:** RF-03, base compartida de RF-07 y RNF-02.
- **Precondiciones y entradas:** P3; producto nuevo; payload con `stock=100`, `quantity=100`, `warehouse_id=999`, `actor_id=999` y `active=0`; SQL directo de saldo negativo/duplicado.
- **Pasos:** crear/editar producto válido; enviar campos prohibidos; intentar saldo `-0.001` y segunda combinación producto/bodega.
- **Esperado:** una única fila en `0.000`, sin stock editable; rechazo de manipulación, negativo y duplicado.
- **Obtenido:** saldo único cero tras creación y edición; campos prohibidos rechazados sin crear producto parcial; PostgreSQL devuelve `23514` para negativo y `23505` para duplicado. DemoSeeder repetido no añade saldos ni stock positivo.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [BaseDataTest](../../tests/Feature/BaseDataTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

## Auditoría, reproducibilidad e interfaz

### M-15. Auditoría y rollback — correcto

- **Requisitos:** infraestructura de RF-12 y RNF-01/RNF-02.
- **Precondiciones y entradas:** P2/P3; auditoría normal y fallo controlado de `AuditService` mediante double de prueba; payload con campos permitidos y contraseñas/tokens de prueba prohibidos.
- **Pasos:** crear producto; inducir fallo de auditoría durante creación de producto y actualización de usuario; comprobar conteos y valor anterior; registrar evento con campos no permitidos.
- **Esperado:** actor/acción/entidad/instante correctos; modificación y auditoría atómicas; ausencia de secretos y cuerpos completos.
- **Obtenido:** evento `created` identifica actor y SKU; el fallo revierte producto y saldo, y conserva el nombre previo del usuario; allowlist solo conserva el nombre autorizado y excluye contraseñas/tokens/request. La inducción ocurre en tests, sin endpoint público de fallo.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [AccessTest](../../tests/Feature/AccessTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt). El rollback de venta es una prueba posterior de Kevin.

### M-16. Seeders repetibles — correcto

- **Requisitos:** RNF-05, RF-02 y RF-03.
- **Precondiciones y entradas:** P2; empresa personalizada, permiso `products.view` retirado al rol `viewer`; producto demo `PRODUCTO-A` editado a precio `31.50`, nombre personalizado e inactivo.
- **Pasos:** repetir `SystemSeeder`; ejecutar DemoSeeder, editar el producto y repetirlo; intentar DemoSeeder con entorno `production`.
- **Esperado:** no duplicar datos ni restaurar permisos/preferencias/precios/estado; saldos demo exclusivamente cero; bloqueo de demo en producción antes de escribir.
- **Obtenido:** conteos estables; empresa personalizada y permiso revocado conservados; producto mantiene precio `31.50`, nombre e inactividad; saldo único cero; intento en producción lanza error y deja conteos iguales.
- **Evidencia:** [CatalogTest](../../tests/Feature/CatalogTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-17. Administrador inicial — correcto

- **Requisitos:** RF-02 y RNF-01/RNF-05.
- **Precondiciones y entradas:** P2; `SystemSeeder`; respuestas ficticias al comando `sicovi:crear-administrador`, correo `comando@example.test` y luego equivalente en mayúsculas.
- **Pasos:** responder al comando; verificar rol/hash; repetir identidad existente y comparar nombre/hash anteriores.
- **Esperado:** crear administrador sin contraseña universal ni sobrescritura; entrada de contraseña oculta mediante `secret`.
- **Obtenido:** cuenta con rol `admin` y hash válido; segundo intento falla conservando nombre y hash. La llamada a `secret` está en el código del comando; la suite simula respuestas y no publica la contraseña ni una grabación de entrada de terminal.
- **Evidencia:** [AccessTest](../../tests/Feature/AccessTest.php), [CreateAdministrator](../../app/Console/Commands/CreateAdministrator.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-18. Protección de pruebas — correcto

- **Requisitos:** RNF-02 y RNF-05.
- **Precondiciones y entradas:** P4; conexiones simuladas SQLite/MySQL/SQL Server; PostgreSQL con `sicovi_local`, `sicovi_verificacion`, `sicovi_test_copy`, esquema `public` o nulo; conexión válida `sicovi_test/sicovi`.
- **Pasos:** llamar a la guarda con cada identidad; comprobar consulta de identidad efectiva y ausencia de `statement`/`unprepared`; revisar su llamada previa a RefreshDatabase; ejecutar suite con conexión válida.
- **Esperado:** rechazo antes de cualquier limpieza con motor/base/esquema equivocados, sin confiar en un nombre solo configurado.
- **Obtenido:** variantes negativas lanzan excepción; motor equivocado ni siquiera recibe la consulta PostgreSQL; conexión válida pasa. Las variantes negativas son doubles: no se conectó ni limpió `sicovi_local` para demostrar el rechazo.
- **Evidencia:** [DatabaseSafetyTest](../../tests/Feature/DatabaseSafetyTest.php), [DatabaseTestCase](../../tests/DatabaseTestCase.php), [TestDatabaseGuard](../../app/Support/TestDatabaseGuard.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-19. Actualización conservadora — correcto en prueba incremental local

- **Requisitos:** RNF-05.
- **Precondiciones y entradas:** P1; esquema ya instalado, producto editado a `42.75` e inactivo, usuario con nombre modificado, empresa con nombre/teléfono propios y hash de usuario capturado solo para comparación en memoria.
- **Pasos:** ejecutar `migrate --force` sobre la instalación existente y `SystemSeeder`; comparar conteos, atributos y hash.
- **Esperado:** conservar registros y configuración sin `migrate:fresh`, DemoSeeder obligatorio ni regenerar claves.
- **Obtenido:** comando correcto, conteos estables, precio `42.75`, estado, nombres, teléfono y hash intactos. En esta corrida `migrate` reaplica el procedimiento con las migraciones actuales, sin una migración nueva de otro módulo. Es una verificación automatizada en `sicovi_test`, no una restauración, actualización en otra PC ni garantía sobre migraciones futuras.
- **Evidencia:** [BaseDataTest](../../tests/Feature/BaseDataTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt).

### M-20. Interfaz y compilación — parcial: automatización correcta, revisión visual acotada

- **Requisitos:** RNF-04 y RF-01/RF-02/RF-03.
- **Precondiciones y entradas:** P2 con DemoSeeder y P5; recursos locales de Vite/Bootstrap; formularios y filtros con arrays inválidos; iframe de 360 x 800 CSS px.
- **Pasos:** renderizar inicio, cuenta, productos (lista/alta/detalle/edición), usuarios, roles, configuración y listado/alta/edición de los cuatro catálogos; enviar tipos inválidos; compilar frontend; acceder mediante navegador y revisar adaptación móvil.
- **Esperado:** respuestas correctas con SICOVI, errores de validación en vez de fallos internos, recursos compilables y ausencia de desbordamiento horizontal general en tamaños de referencia.
- **Obtenido:** los 24 destinos de InterfaceTest responden HTTP 200, muestran SICOVI y no contienen `Stack trace`; arrays en catálogo/producto/filtros se rechazan con HTTP 422. Build conservado en frontend-build.txt, correcto con Vite 7.3.7 y Bootstrap 5.3.8. Login e inicio autenticado comprobados en `sicovi_verificacion`. La captura móvil corresponde al formulario de producto dentro de iframe de 360 x 800 CSS px, escala visual 75%; registra ancho de contenido/desplazamiento 345 px y sin desborde horizontal. No es prueba en un teléfono físico.
- **Pendiente de cierre:** revisión exhaustiva de teclado/foco, datos largos y estados visuales de todos los formularios. El render automatizado no sustituye esas comprobaciones. Escritorio 1366 x 768 revisado: contenido/desplazamiento1351px, sin desborde horizontal; móvil360 x 800:345px. Menú móvil expandido correctamente. Treinta rutas, caché Blade y lint de las vistas compiladas correctos.
- **Evidencia:** [InterfaceTest](../../tests/Feature/InterfaceTest.php), [CatalogTest](../../tests/Feature/CatalogTest.php), [phpunit.txt](../evidencias/manuel/phpunit.txt), [inicio.png](../evidencias/manuel/inicio.png), [movil-360x800.png](../evidencias/manuel/movil-360x800.png), [escritorio-1366x768.png](../evidencias/manuel/escritorio-1366x768.png), [rutas.txt](../evidencias/manuel/rutas.txt), [frontend-build.txt](../evidencias/manuel/frontend-build.txt).

## Verificaciones complementarias y pendientes

| Comprobación | Estado registrado | Alcance de la evidencia |
| --- | --- | --- |
| PHPUnit | Correcto: 120/526, 32.976 s. | Salida conservada; PHP 8.4.12. |
| Carrera de administración | Correcta. | Dos conexiones PostgreSQL, contención observada y estado final en JSON sanitizado. |
| `composer validate` y requisitos de plataforma | Correctos, reportados por la ejecución local. | Transcripts: composer-validacion.txt y plataforma.txt. |
| `npm run build` | Correcto, reportado por la ejecución local. | Vite 7.3.7 y Bootstrap 5.3.8; no confundir con una auditoría completa de navegadores. |
| Rutas y caché de Blade | Correctas: 30 rutas y Blade compilado/lint correcto. | Recuento reportado: 30 rutas; el render de InterfaceTest sí está cubierto por PHPUnit. |
| Navegador local | Comprobación parcial correcta. | Inicio autenticado, listado1366x768 y formulario/menú360x800 sin desborde horizontal. Iframes al75%; no dispositivos físicos. |
| Instalación de un compañero en otra PC | Pendiente, no ejecutada. | RNF-05 no queda demostrado externamente por la instalación local. |
| Restauración cronometrada en base vacía | Pendiente, no ejecutada. | No se declara cumplimiento de RNF-06 ni del objetivo de 30 minutos. |
| Rendimiento comercial RNF-03 | Pendiente de módulos y carga acordada. | No existe benchmark de 10,000 movimientos/ventas en esta fase. |
| Ventas, compras, stock comercial y anulaciones | No implementados ni ejecutados por Manuel. | Permanecen asignados a Yourguen, Kevin y Luis. |

No hay fallos de PHPUnit en la salida final conservada. Los pendientes anteriores se mantienen abiertos y no se convierten en aprobaciones del relevo, cumplimiento global o preparación para producción.

Regresión adicional M-09: marcas «Marca Ágil», «marca ágil» y «MARCA ÁGIL» se consideran duplicadas tanto en HTTP como en PostgreSQL17, incluso con localeC. Migración incremental000002 aplica collationUnicode pg_c_utf8 sin fusionar filas.
