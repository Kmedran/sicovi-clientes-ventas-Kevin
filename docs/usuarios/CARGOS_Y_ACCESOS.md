# Cargos, usuarios y casillas de acceso

Actualizaciones MANUEL-02 y MANUEL-04, solicitadas por el usuario el 19/10/2026. Complementan la entrega base MANUEL-01 y no incorporan operaciones comerciales de otros integrantes. La validación de ejecución del nuevo acceso por usuario de MANUEL-04 está pendiente de registrar; los resultados anteriores se conservan como evidencia de sus respectivas versiones.

## Cargos disponibles

| Cargo | Accesos predeterminados de los módulos actuales |
| --- | --- |
| Administrador | Todos los permisos actuales: cuentas, roles, empresa, productos y catálogos. |
| Vendedor | Consulta de productos y catálogos, sin costos ni modificaciones. |
| Supervisor | Consulta de productos, costos, catálogos y configuración; sin administrar usuarios o modificar información. |
| Bodega | Consulta de productos y catálogos, sin costos. Sus operaciones de inventario las añadirá Yourguen. |
| Compras | Mantenimiento de productos y catálogos con acceso a costos. El código estable es purchasing; una instalación anterior puede conservar el nombre Compras e inventario. |
| Consulta | Lectura de productos y catálogos, sin costos ni modificaciones. |

Los cargos de trabajo son independientes de los cuatro integrantes del proyecto. Las compras, ventas, existencias operativas y anulaciones todavía pertenecen a las siguientes entregas. Cuando existan esos módulos, sus autores añadirán permisos, Gates y asignaciones iniciales explícitas sin restablecer decisiones administrativas anteriores.

## Crear o editar un usuario

1. Ingrese como administrador y abra Usuarios → Nuevo usuario o Editar.
2. Escriba nombre completo, nombre de usuario y correo. El usuario y el correo se guardan en minúsculas y sin espacios exteriores, como campos independientes.
3. Seleccione un cargo. Las casillas muestran sus permisos predeterminados.
4. Marque o desmarque las acciones necesarias para esa persona. La casilla de un grupo selecciona o quita sus acciones.
5. Guarde. La personalización se aplica a ese usuario; los demás usuarios del mismo cargo conservan sus propios accesos.

Cambiar el cargo carga los permisos predeterminados del nuevo cargo. El botón Usar permisos del cargo permite restaurarlos antes de guardar. En una edición sin cambio de cargo, se muestran las asignaciones existentes, incluso si la cuenta está inactiva. Las cuentas y roles inactivos no pueden operar aunque sus casillas estén marcadas.

Ejemplo: dos vendedores comparten los permisos iniciales. Al marcar Consultar costos de referencia solo en uno de ellos, ese vendedor obtiene el permiso y el otro no cambia. Al desmarcar Consultar marcas, se revoca únicamente en esa cuenta. Los accesos se verifican en el servidor en cada solicitud; ocultar botones no sustituye esa comprobación.

La configuración de un cargo en Roles modifica su base para los usuarios que la heredan. Las excepciones individuales explícitas prevalecen. El sistema conserva al menos un administrador activo con capacidad efectiva para recuperar la gestión de usuarios y roles; una modificación que lo impida se rechaza sin cambios parciales.

## Ingresar con usuario y contraseña

En la pantalla de acceso escriba el **nombre de usuario**, por ejemplo `adminm`, y la contraseña. Los indicadores USUARIO y CONTRASEÑA desaparecen cuando se enfoca cada campo. El login ya no pide el correo ni muestra el logo; conserva la comprobación de cuentas activas, permisos y límites de intentos.

El nombre de usuario es único, tiene entre 3 y 40 caracteres ASCII y comienza por letra o número. Después puede incluir letras, números, punto (`.`), guion (`-`) o guion bajo (`_`). Se guarda en minúsculas: `ADMINM` se normaliza a `adminm`. No admite `@`, espacios interiores ni letras con tildes. Puede crearse y editarse desde Usuarios.

Usuario y correo son independientes. Cambiar el correo no cambia el nombre de usuario ni sus permisos; cambiar el usuario tampoco cambia el correo. El cargo no se deduce de ninguno de los dos: escribir un nombre que empieza con `admin` no concede privilegios. Las relaciones y los permisos efectivos de la cuenta determinan el acceso.

El correo continúa siendo un dato único de la cuenta y se guarda normalizado. Los correos de ejemplo no representan buzones creados ni envíos de mensajes. Para iniciar sesión se usa la columna Usuario de la siguiente tabla, sin `@gmail.com`.

Las cuentas normales mantienen la política de contraseña de 12 caracteres, mayúscula, minúscula, número y símbolo; su contraseña se guarda con hash. El administrador puede restablecerla y revocar las sesiones. Nunca se muestra el hash o la contraseña en la auditoría.

## Cuentas de demostración solicitadas

La inicial elegida es m, de Manuel. Estas son credenciales públicas de prueba local:

| Cargo | Usuario | Correo conservado | Contraseña de demostración |
| --- | --- | --- | --- |
| Administrador | adminm | adminm@gmail.com | admin |
| Vendedor | vendedorm | vendedorm@gmail.com | vendedor |
| Supervisor | supervisorm | supervisorm@gmail.com | supervisor |
| Bodega | bodegam | bodegam@gmail.com | bodega |

Se crean de forma explícita con `php artisan sicovi:crear-usuarios-demo --inicial=m`, después de migrar y ejecutar SystemSeeder. Requieren `SICOVI_DEMO_ACCOUNTS=true`, entorno local/testing, host loopback y conexión efectiva PostgreSQL a sicovi_local/sicovi o sicovi_test/sicovi respectivamente. La opción está desactivada en los ejemplos de configuración.

Cada cuenta queda marcada is_demo. Tanto el login como las sesiones existentes y la autorización rechazan estas cuentas fuera del entorno permitido, incluso si se copia la base a producción. Desactivar la bandera también revoca su acceso operativo. Cree antes un administrador normal con contraseña propia para dejar de utilizar las cuentas de demostración.

Repetir el comando conserva contraseñas, nombres, usuarios existentes, cargos, permisos personalizados y estados; no restablece cuentas existentes. Si un usuario o correo previsto entra en conflicto con otra cuenta, el lote se rechaza sin sobrescribir registros. La actualización de una cuenta demo anterior la hace la migración de usuarios, sin necesidad de recrearla.

## Persistencia y continuidad

- users guarda nombre de usuario, correo e identidad, cargo, estado, hash e indicador is_demo.
- roles, permissions y role_permissions definen las plantillas por cargo.
- user_permissions guarda únicamente las diferencias individuales: allowed=true concede y allowed=false deniega; sin fila se hereda del cargo.
- sessions conserva las sesiones de desarrollo en PostgreSQL.
- audit_events registra cambios de identidad, cargo y permisos permitidos; nunca contraseñas.

Las migraciones 000003 y 000004 son incrementales; conservan usuarios existentes y sus contraseñas. La primera normaliza correos existentes y añade su CHECK y la marca de demostración; la segunda crea las excepciones de permisos sin alterar asignaciones anteriores. No requieren reconstruir la base ni regenerar APP_KEY.

La migración 000005 añade el nombre de usuario. Asigna valores en orden de ID a partir de la parte anterior a `@` del correo, convertida a ASCII y minúsculas, y sustituye caracteres incompatibles por guiones. Si el resultado es muy corto, usa el prefijo `usuario-`; si queda vacío, usa `usuario`. Las colisiones reciben sufijos como `-2` o `-3`, con un máximo final de 40 caracteres. Los correos demo actuales generan naturalmente `adminm`, `vendedorm`, `supervisorm` y `bodegam`. El administrador puede revisar y editar el usuario asignado en el mantenimiento.

Esta migración conserva correos, hashes, estados, cargos y excepciones de permisos. El comando para crear un administrador normal ahora solicita Nombre de usuario entre Nombre completo y Correo electrónico. Continúa pidiendo una contraseña fuerte con entrada oculta.
