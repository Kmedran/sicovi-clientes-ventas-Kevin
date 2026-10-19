# Contratos de continuidad de SICOVI

Fecha: 19 de octubre de 2026. Referencia local: MANUEL-01.

Este documento define las dependencias entre la base de Manuel y los módulos siguientes. Los contratos futuros expresan obligaciones de implementación; no representan servicios ya disponibles. Los nombres concretos de métodos deben coincidir con el código y las pruebas de cada entrega antes de considerarlos una API estable.

## Responsables y fronteras

| Responsable | Entrega | Dependencias y condición de recepción |
| --- | --- | --- |
| Manuel | Acceso, usuarios, roles/permisos, configuración, catálogos, productos, auditoría base y utilidades. | Migraciones, seeders, recursos, documentación y resultados reales de pruebas. La ejecución y recepción externa deben verificarse antes de declararlas terminadas. |
| Yourguen | Proveedores, compras, recepción e inventario: saldos, entradas, ajustes, kardex y movimientos. | Reutilizar acceso, productos, contexto y auditoría; implementar el servicio común de inventario. |
| Kevin | Clientes, borradores y confirmación de venta, totales y comprobante interno. | Coordinar venta, detalles, inventario, auditoría e idempotencia en una transacción. |
| Luis | Anulaciones, consultas, panel, reportes e integración. | Reversión única sobre movimientos originales, revisión de permisos y coordinación de pruebas integradas. |

No corresponde a Manuel crear CRUD de clientes/proveedores, migraciones comerciales completas, controladores vacíos ni respuestas ficticias que simulen los módulos siguientes. Cada autor ejecuta y corrige sus propias pruebas.

## Base compartida de esta fase

La base prevista comprende `companies`, `branches`, `warehouses`, `users`, `roles`, `permissions`, `role_permissions`, `user_permissions`, `sessions`, `categories`, `subcategories`, `brands`, `units`, `products`, `audit_events` e `inventory_balances`, más las auxiliares realmente necesarias. Su estructura definitiva debe consultarse en las migraciones y el diccionario de la entrega.

`inventory_balances` contiene una combinación única producto/bodega con cantidad inicial cero. No contiene una entrada comercial ni habilita operaciones de stock. Crear producto, saldo cero y auditoría es una operación atómica. Yourguen debe conservar esa unicidad y no asumir que puede bloquear un saldo inexistente.

Las tablas futuras `customers`, `suppliers`, `purchases`, `purchase_items`, `sales`, `sale_items`, `inventory_adjustments`, `inventory_adjustment_items`, `inventory_movements` e `idempotency_records` pertenecen a sus módulos. No se crean relaciones hacia tablas que aún no existen. Las FK de movimientos deben vincular un origen verificable; un identificador libre de documento no sustituye la integridad referencial.

## Contexto organizativo

El contexto compartido resuelve la única empresa, sucursal y bodega operativa mediante configuración y relaciones reales. No se debe hardcodear un ID igual a 1 ni aceptar un `warehouse_id` del navegador sin validar su pertenencia y autorización.

Entradas: usuario autenticado y contexto de la operación. Salida: organización y bodega válidas para la V1. Si no hay configuración operativa válida, devolver un conflicto de configuración comprensible; no elegir silenciosamente otro registro.

La aplicación conserva instantes en UTC y presenta fechas en `America/Guatemala`; moneda GTQ. Los módulos nuevos reutilizan esa configuración.

## Permisos y actor

La autorización es por acción, usando el usuario de la sesión y sus permisos vigentes. El navegador no determina el actor, rol o autorización. Todos los endpoints nuevos deben proteger lectura, escritura y acceso a recursos por ID.

Permisos base de referencia:

- `users.view`, `users.create`, `users.update`, `users.deactivate`, `users.reset_password`.
- `roles.view`, `roles.manage`.
- `settings.view`, `settings.update`.
- `products.view`, `products.create`, `products.update`, `products.deactivate`, `products.view_cost`.
- `categories.view`, `categories.manage`, `subcategories.view`, `subcategories.manage`.
- `brands.view`, `brands.manage`, `units.view`, `units.manage`.

Cada módulo añade sus permisos con códigos estables, una migración o carga de evolución explícita y asignaciones deliberadas. Añadir una acción no autoriza concederla indiscriminadamente a todos los roles ni restablecer asignaciones personalizadas. Las revocaciones deben surtir efecto sin cachés de permisos obsoletas.

Los cambios en roles, estado y permisos capaces de dejar al sistema sin administrador deben usar la misma protección transaccional y de bloqueo que la administración base. Los módulos comerciales no modifican esa regla.

El costo de referencia solo puede consultarse con `products.view_cost`. No reutilizar una serialización amplia de producto que lo exponga a vendedor o consulta.

## Aritmética decimal y cantidades

El contrato compartido utiliza cadenas decimales normalizadas y BCMath. Ninguna operación convierte importes a float para calcularlos o validarlos.

| Dato | Regla |
| --- | --- |
| Cantidad y saldo | `NUMERIC(14,3)`; escala compatible con `units.decimal_places`, entre 0 y 3. |
| Precio y costo de catálogo | `NUMERIC(14,2)`; cero permitido, nunca negativo. |
| Cantidad comercial | Positiva, en rango y compatible con la unidad. |
| Importe futuro de línea y total | `NUMERIC(16,2)`, validado antes de persistir. |
| Cálculo | Redondear cada cantidad por precio half-up a dos decimales y sumar las líneas ya redondeadas. |
| Presentación | Separada del valor interno, por ejemplo `Q 1,234.56`. |

Ejemplos de aceptación: `2 × 25.00 = 50.00`; `0.333 × 10.00 = 3.33`; `1.005 × 1.00 = 1.01`. Una cantidad `1.5` con unidad de cero decimales se rechaza. No se aceptan notación científica, formatos ambiguos, escalas excesivas ni desbordamientos; no se corrigen silenciosamente mediante redondeo.

## Auditoría compartida

Entradas: actor autenticado, acción, entidad e identificador, motivo cuando aplica y cambios expresamente permitidos. La fecha se determina en el servidor. El servicio no recibe el request completo como payload de auditoría.

El coordinador de la operación abre la transacción y el evento se guarda dentro de ella. Auditoría no confirma de forma independiente. Si falla un evento obligatorio, la operación completa debe fallar y revertirse. No capturar la excepción para devolver éxito parcial.

Nunca se registran contraseñas, hashes, tokens, cookies, APP_KEY, credenciales ni datos ajenos a la allowlist. Un evento de acceso fallido es un evento de seguridad, no una operación comercial confirmada. La prueba de fallo de auditoría se induce únicamente en pruebas; no se añade un endpoint público para provocar fallos.

## Servicio común de inventario: pendiente de Yourguen

Este servicio no se implementa en la fase de Manuel. No debe añadirse un stub que devuelva éxito o cantidades ficticias para satisfacer una dependencia.

Entradas exigidas:

- Actor autorizado obtenido del servidor.
- Bodega operativa validada.
- Productos y cantidades normalizadas según su unidad.
- Motivo y documento de origen existente y verificable.
- Identidad persistente de operación cuando se requiera idempotencia o reversión.

Comportamiento:

1. Participar en la transacción coordinada por compra, venta, entrada, ajuste o anulación; no hacer commits propios.
2. Obtener/bloquear saldos mediante una estrategia segura que contemple filas aún inexistentes.
3. Adquirir bloqueos en un orden estable por ID de producto, revalidando disponibilidad después del bloqueo.
4. Rechazar un saldo final negativo y cantidades incompatibles con la unidad.
5. Actualizar cada saldo y crear su movimiento de forma atómica, con actor, motivo y origen.
6. Mantener movimientos confirmados inmutables; corregir mediante reversión identificada y única.
7. Devolver resultados y errores de negocio explícitos; propagar fallos técnicos que obliguen al rollback.

El saldo se debe conciliar con los movimientos. Una confirmación de compra ya es recepción completa y no genera además una segunda entrada manual. El costo de referencia de producto no equivale a una valoración contable de inventario.

Antes de habilitar cualquier movimiento, Yourguen debe incorporar la consulta de historial que bloquea cambios de unidad base de productos con movimientos. La base solo puede validar precisión y coherencia del saldo actual mientras no exista ese historial.

## Coordinación futura de compras y ventas

Yourguen coordina compra, detalles, saldos, movimientos, auditoría y resultado idempotente en una única transacción. Sus estados son borrador, confirmada y anulada; confirmar implica recepción completa una sola vez. No incluye crédito, recepciones parciales ni cambios retroactivos de costos confirmados.

Kevin coordina venta, detalle, saldo, movimientos, auditoría y resultado idempotente en una única transacción. El borrador no consume ni reserva stock. El servidor obtiene precio y calcula totales; si un precio cambió, se exige revisar la nueva condición antes de confirmar. El documento conserva sus datos comerciales históricos. Consultar o imprimir el comprobante interno no vuelve a descontar existencias.

La concurrencia de venta requiere bloqueo dentro de la transacción y revalidación posterior: dos solicitudes distintas por la última unidad permiten una confirmación y un rechazo, con saldo cero y una salida. Los reintentos ante errores transitorios son limitados y solo se hacen cuando conservan atomicidad e idempotencia.

Los borradores deben tener una versión entera que se valide e incremente al guardar para rechazar ediciones obsoletas. Descartar un borrador no es anular un documento confirmado.

## Idempotencia y reversión: contratos futuros

La clave idempotente es persistente y única dentro de un ámbito de usuario y acción. Se vincula al documento y a una huella del contenido normalizado. Misma clave y contenido devuelve el resultado sin repetir efectos; misma clave con contenido distinto produce conflicto. La unicidad y la carrera entre solicitudes deben resolverse también en PostgreSQL. Deshabilitar un botón no cumple esta garantía.

Luis implementa anulación autorizada con motivo obligatorio, conservación del original, actor y fecha. La reversión debe vincular cada movimiento original y tener una restricción única que impida repetirla. Un reintento no modifica nuevamente stock; un documento anulado no se confirma otra vez. Anular una compra exige saldo suficiente para revertir la recepción sin inventar unidades ni eliminar consumos posteriores.

## Respuestas de error

| Situación | Formularios web | JSON cuando corresponda |
| --- | --- | --- |
| Sin autenticación | Redirección al login. | HTTP 401. |
| Sin permiso | Página o mensaje de acceso denegado. | HTTP 403. |
| Recurso inexistente o inaccesible conforme a la política | Respuesta coherente sin filtrar información privada. | HTTP 404 cuando corresponda. |
| Campos inválidos | Volver al formulario, conservar entradas seguras y asociar errores a los campos. | HTTP 422 con errores de validación. |
| Relación, estado, versión o clave idempotente incompatible | Mensaje de conflicto recuperable, sin escrituras parciales. | HTTP 409. |
| Fallo técnico | Mensaje genérico y registro interno sanitizado. | HTTP 500 sin SQL, stack trace ni secretos. |

No se presenta un resultado satisfactorio antes del commit. Los módulos mantienen mensajes en español y los mismos componentes de campos, alertas, filtros y paginación.

## Instalación, evolución y evidencia de recepción

Cada integrante utiliza credenciales y base locales propias. GitHub entrega archivos versionados; no sincroniza filas ni requiere que la computadora del compañero permanezca encendida.

La recepción usa migraciones, seeders, `.env.example`, locks y README. Se conserva `.env` y APP_KEY en actualizaciones. Los nuevos módulos se entregan mediante migraciones incrementales y notas de cambio que indican el efecto sobre registros existentes; no requieren reconstruir la base ni restaurar indiscriminadamente datos demo.

Recorrido comercial de referencia para etapas posteriores: producto entero a `25.00`, inventario inicial `100`, compra de `20` y venta de `5`: `100 → 120 → 115`; el reintento mantiene `115`; la anulación de venta vuelve a `120` una sola vez. Este recorrido no se considera ejecutado en la fase de Manuel y no autoriza precargar stock positivo desde su seeder.

Quedan pendientes de evidencia real al redactar este contrato: versiones finales de runtimes/dependencias, conexión PostgreSQL 17, ejecución de la suite, compilación, inspección visual y recepción en otra computadora. Deben actualizarse los estados de pruebas y la entrega a Yourguen con los resultados efectivamente obtenidos. Las pruebas de última unidad, idempotencia comercial y anulaciones se ejecutan cuando existan sus módulos; no se atribuyen a la infraestructura base.

## Actualización MANUEL-02: autorización individual

Los seis cargos base incluyen Supervisor y Bodega. user_permissions almacena diferencias por persona frente a su cargo (allowed=true/false); sin fila se hereda. Use siempre User::hasPermission, Gate o Policy para autorizar: consultar únicamente role_permissions omitiría las excepciones. User::effectivePermissionCodes devuelve asignaciones para editar, aun con usuario/rol inactivo; por sí solo no autoriza una operación. hasPermission sí exige estado activo y condiciones especiales de cuentas demo.

La UI marca los defaults del cargo y permite agregar/quitar para ese usuario. La operación guarda identidad, cargo, diferencias y auditoría en una transacción con lock 74126001. Toda futura mutación de administración debe preservar al menos un administrador activo con permisos efectivos críticos. Antes de desplegar, crear un administrador normal y desactivar las cuentas de demostración.

Correos users se almacenan minúsculos/trim con CHECK PostgreSQL; las cuentas normales mantienen contraseñas fuertes. Las cuatro cuentas de claves públicas solicitadas para pruebas quedan etiquetadas is_demo y solo se autentican/operan en conexión local expresamente permitida. No copiar sus reglas de excepción a compras o ventas.
