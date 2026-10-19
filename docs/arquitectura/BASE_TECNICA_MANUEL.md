# Base técnica de Manuel

Fecha: 19 de octubre de 2026. Referencia local: MANUEL-01.

Este documento registra las decisiones de la primera entrega de SICOVI y sus límites. Su existencia no certifica que una migración, instalación o prueba haya terminado: los resultados efectivos deben constar en la matriz de pruebas y las evidencias de cierre.

## Alcance y responsabilidad

Manuel entrega la base del sistema, acceso y catálogos: autenticación, usuarios, roles, permisos, empresa, sucursal y bodega inicial, categorías, subcategorías, marcas, unidades y productos. También prepara auditoría base, utilidades decimales, datos reproducibles e instrucciones de instalación y actualización.

Corresponde a RF-01, RF-02 y RF-03; aporta la infraestructura de RF-12. No se declara completa la auditoría comercial por disponer de su servicio base.

Proveedores, compras e inventario corresponden a Yourguen; clientes y ventas a Kevin; anulaciones, consultas comerciales e integración a Luis. La fase no incluye sus tablas operativas, pantallas, servicios ni pruebas de venta. El contrato de continuidad describe esas dependencias sin simularlas.

## Arquitectura elegida

Se utiliza un monolito Laravel por capas, con páginas Blade y formularios de sesión. Esta organización conserva un único despliegue y permite coordinar escrituras en una transacción PostgreSQL. No requiere una SPA, API pública, JWT, microservicios ni un proveedor externo de identidad.

| Capa | Ubicación | Responsabilidad |
| --- | --- | --- |
| Presentación | `routes`, `app/Http`, `resources/views` | Rutas protegidas, validación de solicitudes, controladores pequeños y presentación en español. |
| Aplicación y negocio | `app/Services`, `app/Support`, `app/Policies` | Reglas de autorización, coordinación transaccional, contexto organizativo, decimales y auditoría. |
| Persistencia | `app/Models`, `database/migrations`, `database/seeders` | Eloquent, relaciones, consultas parametrizadas, restricciones e inicialización reproducible. |
| Verificación | `tests` y `docs/pruebas` | Pruebas de utilidades, integración real PostgreSQL y evidencia del alcance implementado. |

No se añaden repositorios genéricos ni interfaces vacías para envolver Eloquent. JavaScript puede mejorar interacción y prevenir envíos repetidos; la validación, autorización y exactitud de los datos se resuelven en el servidor. La comparación académica de alternativas arquitectónicas corresponde a Kevin.

## Stack acordado y estado de comprobación

| Componente | Referencia del equipo | Estado al redactar esta nota |
| --- | --- | --- |
| PHP | 8.4.x; extensiones Laravel, PDO PostgreSQL y BCMath | PHP 8.4.12 con BCMath y PDO PostgreSQL verificados; ini privado transmitido mediante PHPRC por scripts/php.ps1. |
| Laravel | 13.x | Laravel13.37.0 fijado en composer.lock. |
| Composer | 2.x | Composer2.9.4; validate y check-platform-reqs correctos, salidas conservadas. |
| PostgreSQL | Servidor 17.x | PostgreSQL 17.11 portable privado en puerto55432; conexión real pgsql y esquema sicovi comprobados. El PostgreSQL16 existente no se modificó. |
| Frontend | Blade, Bootstrap 5.3.x, Vite y plugin compatible | Recursos y dependencias de la fase en preparación; la compilación final debe registrarse. |
| Node.js | 22.x, desde 22.12; npm | La versión realmente utilizada debe registrarse junto con el lock de npm. |

No debe deducirse la versión del servidor PostgreSQL a partir de `psql --version`. Se necesita una consulta a la conexión de la aplicación. La presencia de PostgreSQL 16 en el equipo no convierte esa instalación en el entorno acordado ni autoriza acceder a sus bases.

Los runtimes y datos privados que se preparen dentro de `storage/runtime` son recursos locales ignorados por Git. No se añaden servicios de Windows ni cambios globales de PHP o Apache como requisito de esta entrega. El cierre debe precisar los comandos finalmente utilizados, sin credenciales. Este documento no afirma que el runtime privado ya esté instalado.

## Persistencia y evolución

- Desarrollo usa `sicovi_local`; pruebas usan exclusivamente `sicovi_test`; una comprobación de instalación o restauración puede usar `sicovi_verificacion`.
- El esquema de aplicación es `sicovi`. `DB_SCHEMA` debe alimentar el `search_path` real de la conexión; las tablas auxiliares, sesiones y registro de migraciones usan ese mismo esquema.
- La aplicación utiliza un usuario propio, como `sicovi_app`, sin utilizar habitualmente el superusuario administrativo.
- La preparación de usuario, bases y esquema debe realizarse en una sesión autorizada y documentada. Los ejemplos nunca incluyen contraseñas reales.
- Cada cambio permanente de esquema se entrega mediante migración. Después de compartir una migración aplicada, la evolución se hace mediante otra migración.
- PK, FK, unicidad, CHECK e índices complementan las validaciones de aplicación. La relación categoría/subcategoría se protege también mediante una restricción relacional de PostgreSQL.
- No se elimina historial con cascadas ni se usan eliminaciones físicas de catálogos como operación de mantenimiento.

Los registros organizativos de V1 representan una sola empresa, sucursal y bodega operativa. Se resuelven por configuración y relaciones reales, sin asumir identificadores iguales a 1. Sus formularios no multiplican sedes ni inutilizan la única bodega.

Las actualizaciones conservan `.env`, `APP_KEY` y datos existentes. No utilizan `migrate:fresh`, `db:wipe`, borrado de bases ni truncados. `SystemSeeder` y `DemoSeeder` crean datos con claves estables sin restaurar indiscriminadamente valores administrados. Los datos demo no son un paso obligatorio de cada actualización.

## Autenticación y autorización

Se utiliza el guard de sesión de Laravel y hash nativo de contraseñas. Las sesiones se persisten en PostgreSQL; caché local en archivos y trabajos síncronos evitan servicios adicionales para esta fase.

El login limita intentos, usa mensajes que no revelan si existe una cuenta y regenera la sesión al autenticar. El logout invalida la sesión y renueva el token CSRF. Un usuario inactivo pierde acceso operativo aunque conserve una sesión anterior. No hay registro público ni recuperación por correo sin un servicio real configurado.

Cada usuario tiene un rol, y cada rol varios permisos de acción. Los códigos estables se conservan al cambiar nombres visibles. Middleware, Gates o Policies aplican la autorización en el servidor y también cuando se accede directamente a un recurso por ID. Ocultar opciones de navegación es una ayuda de interfaz, no una autorización.

El administrador puede mantener usuarios, roles, permisos, configuración y catálogos. Compras e inventario recibe las acciones de catálogos autorizadas; vendedor y consulta tienen lectura expresamente asignada. El costo de referencia requiere `products.view_cost` y no se entrega en HTML, campos ocultos ni JSON cuando falta ese permiso.

La protección del último administrador abarca estado, rol y permisos necesarios para recuperar la administración. Todos los caminos que modifican esa capacidad deben utilizar una transacción y la misma estrategia de bloqueo; contar administradores fuera de esa sección crítica es insuficiente. Las pruebas deben distinguir la regla funcional de su comprobación concurrente real.

La cuenta inicial se crea mediante `sicovi:crear-administrador`, con contraseña de entrada oculta, validación de unicidad y sin sobrescribir cuentas existentes. No se incorpora una contraseña universal al repositorio. Los restablecimientos administrativos son controlados y revocan sesiones según el comportamiento documentado de la implementación.

## Productos y precisión

Los códigos y nombres se normalizan de forma consistente; se rechazan duplicados equivalentes por espacios exteriores o diferencias triviales de mayúsculas. El SKU es obligatorio y único. El barcode es texto para conservar ceros iniciales; un valor vacío se normaliza a `NULL` y un valor informado es único.

Las cantidades usan `NUMERIC(14,3)` y precio/costo `NUMERIC(14,2)`. Cada unidad define entre cero y tres decimales. Se rechazan formato ambiguo, notación científica, valores fuera de rango y precisión superior a la permitida. Cero es válido en precio, costo y stock mínimo; una cantidad futura de operación comercial deberá ser positiva.

Las utilidades operan con cadenas y BCMath, sin convertir los cálculos monetarios a float. Para los contratos futuros de documentos, cada línea se calcula como cantidad por precio y se redondea half-up a dos decimales; el total suma líneas ya redondeadas. Los importes futuros de línea y documento se prevén como `NUMERIC(16,2)`.

Crear un producto también crea una única fila de `inventory_balances` en cero para la bodega operativa. Ambas escrituras y su auditoría obligatoria se confirman juntas. Ese cero no es una entrada de inventario y ningún formulario de producto puede modificarlo.

Los catálogos inactivos siguen siendo legibles en relaciones existentes y no se asignan como relaciones nuevas. Su edición debe mostrar la selección actual sin sustituirla silenciosamente. Se bloquea la desactivación de categoría, marca o unidad con productos activos, y el cambio de precisión de una unidad ya utilizada.

Antes de las primeras operaciones de inventario, Yourguen debe activar la comprobación de historial que impide cambiar la unidad base de un producto con movimientos. En esta fase se valida coherencia y precisión del saldo existente; no se afirma probada una regla sobre movimientos todavía inexistentes.

## Auditoría y tiempo

Cada operación importante de la fase registra actor del servidor, instante UTC, acción, entidad e identificador, motivo cuando corresponde y cambios relevantes incluidos en una allowlist. El evento obligatorio participa en la transacción de negocio: un fallo al registrarlo revierte esa modificación.

No se registran contraseñas, hashes, tokens, cookies, claves, credenciales ni solicitudes completas. Los intentos fallidos de acceso pueden ser eventos de seguridad separados; no se presentan como operaciones comerciales confirmadas. Esta fase no incluye pantalla para borrar auditoría ni el módulo final de consulta.

Los instantes se persisten en UTC y se muestran en `America/Guatemala`. El formato monetario de presentación es independiente de los valores internos exactos.

## Verificación y límites de la evidencia

Las pruebas de persistencia utilizan PostgreSQL real. La guarda debe comprobar el driver y el nombre efectivo `sicovi_test` antes de `RefreshDatabase`, migraciones de prueba o limpieza, incluso con configuración cacheada. Las utilidades puras pueden probarse sin base de datos.

La evidencia final debe cubrir los casos M-01 a M-20 aplicables: acceso y sesiones, permisos directos, ocultación de costos, integridad y unicidad, precisión, saldo cero, auditoría y rollback, seeders, administrador inicial, protección de pruebas, actualización conservadora y funcionamiento de interfaz. También debe registrar `composer validate`, requisitos de plataforma y compilación Vite.

Los estados de cada caso son diseñado, ejecutado correctamente, fallido o bloqueado; no se deducen de la existencia de archivos. La revisión visual debe indicar qué pudo comprobarse en 1366 x 768 y 360 x 800. La recepción en otra computadora queda pendiente hasta que un compañero la realice y aporte evidencia. Finalizar esta base no certifica el proyecto completo ni un despliegue de producción.

## Referencias técnicas

- [Laravel 13: documentación](https://laravel.com/docs/13.x).
- [PostgreSQL 17: esquemas](https://www.postgresql.org/docs/17/ddl-schemas.html).
- [PostgreSQL 17: bloqueo explícito](https://www.postgresql.org/docs/17/explicit-locking.html).
- [PostgreSQL: distribución para Windows y opción ZIP de EDB](https://www.postgresql.org/download/windows/).


NormalizaciónUnicode: la migración000002 fija pg_c_utf8 en columnas relevantes y conserva datos; detecta duplicados preexistentes antes de reconstruir índices. Se verificó unicidad entre mayúsculas/minúsculas con tildes. [Referencia oficial PostgreSQL17](https://www.postgresql.org/docs/17/collation.html). ProductPolicy participa en creación/consulta/edición/estado por recurso; los Gates cubren permisos de acción del resto de pantallas.

## Evolución MANUEL-02

Se añaden cargos Supervisor/Bodega, normalización SQL de correos, cuentas demo locales explícitas y permisos individuales por excepción a los defaults del rol. La autorización combina el cargo con allowed/denied persistidos en user_permissions y revalida usuario/rol activos sin caché. Crear/editar usa checkboxs de acciones existentes; no se anticipan módulos comerciales.

El comando de demostración es una excepción local solicitada por el usuario: is_demo + SICOVI_DEMO_ACCOUNTS y guarda de conexión efectiva. El login y middleware rechazan demos fuera de local/testing; se conserva la política fuerte para cuentas ordinarias. La protección de administración comprueba permisos efectivos, no solo pertenencia al rol. Ver resultados actuales en evidencia roles_usuarios.
