# Diccionario de la base de Manuel

Referencia local MANUEL-01 · PostgreSQL 17 · esquema `sicovi`.

Este documento describe las migraciones `2026_10_19_000001_create_sicovi_base_tables.php` y `2026_10_19_000002_use_unicode_catalog_collations.php`. No constituye el modelo completo de SICOVI: todavía no existen tablas de clientes, proveedores, documentos comerciales, movimientos o idempotencia.

## Convenciones

Las tablas con `id` usan `BIGINT` generado por PostgreSQL como clave primaria. Las FK usan `BIGINT`; todas las eliminaciones de registros referenciados se restringen. Ninguna relación elimina historial en cascada. `created_at` y `updated_at`, cuando se indican, son `TIMESTAMP WITH TIME ZONE` anulables de Laravel. La aplicación conserva instantes en UTC.

Los campos `active` son `BOOLEAN NOT NULL DEFAULT true`. Todos los campos son obligatorios salvo que se indique «sí» en nulidad. Las cadenas requeridas de códigos y nombres tienen `CHECK length(btrim(campo)) > 0`. Los modelos normalizan espacios de nombres/códigos, convierten códigos y SKU a mayúsculas y correos a minúsculas. Los índices normalizados usan `lower(regexp_replace(btrim(campo), '\s+', ' ', 'g'))`: diferencias triviales de capitalización, extremos o espacios repetidos no permiten duplicados. No se elimina la distinción entre letras con y sin tilde.

## Organización

| Tabla/campo | Tipo | Nulo | Valor inicial / relación |
| --- | --- | --- | --- |
| companies.id | BIGINT PK | no | Generado |
| companies.trade_name | VARCHAR(150) | no | Nombre comercial |
| companies.legal_name | VARCHAR(200) | sí | Razón social |
| companies.tax_id | VARCHAR(30) | sí | Identificador tributario |
| companies.address | TEXT | sí | Dirección |
| companies.phone | VARCHAR(30) | sí | Contacto |
| companies.email | VARCHAR(254) | sí | Contacto, no credencial |
| companies.currency | CHAR(3) | no | `GTQ`; CHECK exige GTQ |
| companies.display_timezone | VARCHAR(64) | no | `America/Guatemala` |
| companies.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| branches.id | BIGINT PK | no | Generado |
| branches.company_id | BIGINT FK | no | companies.id, RESTRICT |
| branches.code | VARCHAR(30) | no | Único normalizado |
| branches.name | VARCHAR(150) | no | Único normalizado |
| branches.address | TEXT | sí | Dirección |
| branches.phone | VARCHAR(30) | sí | Contacto |
| branches.active | BOOLEAN | no | true; CHECK exige true en V1 |
| branches.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| warehouses.id | BIGINT PK | no | Generado |
| warehouses.branch_id | BIGINT FK | no | branches.id, RESTRICT |
| warehouses.code | VARCHAR(30) | no | Único normalizado |
| warehouses.name | VARCHAR(150) | no | Único normalizado |
| warehouses.location | VARCHAR(255) | sí | Ubicación |
| warehouses.active | BOOLEAN | no | true; CHECK exige true en V1 |
| warehouses.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |

Cada tabla organizativa tiene un índice único sobre la expresión constante `true`, por lo que admite como máximo una fila. `SystemSeeder` crea las tres filas si faltan; la relación real resuelta por `OrganizationContext` permite obtener sus IDs sin asumir el valor 1. Los formularios conservan la sucursal y bodega operativas. El máximo de una fila no sustituye la inicialización: una BD sin seeders todavía puede no tener configuración.

## Acceso

| Tabla/campo | Tipo | Nulo | Restricción / finalidad |
| --- | --- | --- | --- |
| roles.id | BIGINT PK | no | Generado |
| roles.code | VARCHAR(40) | no | Único normalizado; códigos estables admin, purchasing, seller, viewer |
| roles.name | VARCHAR(100) | no | Único normalizado |
| roles.description | TEXT | sí | Descripción |
| roles.active | BOOLEAN | no | true |
| roles.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| permissions.id | BIGINT PK | no | Generado |
| permissions.code | VARCHAR(80) | no | UNIQUE, código de acción estable |
| permissions.name | VARCHAR(150) | no | Etiqueta |
| permissions.group | VARCHAR(50) | no | Agrupación de interfaz |
| permissions.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| role_permissions.role_id | BIGINT FK | no | roles.id, RESTRICT |
| role_permissions.permission_id | BIGINT FK | no | permissions.id, RESTRICT |
| users.id | BIGINT PK | no | Generado |
| users.name | VARCHAR(150) | no | Nombre |
| users.email | VARCHAR(254) | no | Único normalizado |
| users.password | VARCHAR(255) | no | Hash, nunca texto recuperable |
| users.role_id | BIGINT FK | no | roles.id, RESTRICT; un rol por usuario |
| users.active | BOOLEAN | no | true |
| users.last_login_at | TIMESTAMPTZ | sí | Último acceso satisfactorio |
| users.remember_token | VARCHAR(100) | sí | Campo de sesión de Laravel |
| users.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| sessions.id | VARCHAR(255) PK | no | Identificador Laravel |
| sessions.user_id | BIGINT FK | sí | users.id, RESTRICT; sesiones anónimas admitidas |
| sessions.ip_address | VARCHAR(45) | sí | Dirección del cliente |
| sessions.user_agent | TEXT | sí | Agente del cliente |
| sessions.payload | TEXT | no | Estado de sesión; no exponer |
| sessions.last_activity | INTEGER | no | Instante Unix; índice |

`role_permissions` tiene PK compuesta `(role_id, permission_id)` y no contiene timestamps. `sessions.user_id` se indexa por la FK creada mediante Laravel. No hay tablas de restablecimiento por correo, caché o trabajos: no se requieren para la fase, que usa caché en archivos y ejecución síncrona. Las sesiones sí se almacenan en PostgreSQL.

## Catálogos

| Tabla/campo | Tipo | Nulo | Restricción / finalidad |
| --- | --- | --- | --- |
| categories.id | BIGINT PK | no | Generado |
| categories.code | VARCHAR(30) | no | Único normalizado |
| categories.name | VARCHAR(120) | no | Único normalizado |
| categories.description | TEXT | sí | Descripción |
| categories.active | BOOLEAN | no | true |
| categories.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| subcategories.id | BIGINT PK | no | Generado |
| subcategories.category_id | BIGINT FK | no | categories.id, RESTRICT |
| subcategories.name | VARCHAR(120) | no | Único normalizado dentro de su categoría |
| subcategories.description | TEXT | sí | Descripción |
| subcategories.active | BOOLEAN | no | true |
| subcategories.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| brands.id | BIGINT PK | no | Generado |
| brands.name | VARCHAR(120) | no | Único normalizado |
| brands.description | TEXT | sí | Descripción |
| brands.active | BOOLEAN | no | true |
| brands.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| units.id | BIGINT PK | no | Generado |
| units.code | VARCHAR(30) | no | Único normalizado |
| units.name | VARCHAR(100) | no | Único normalizado |
| units.abbreviation | VARCHAR(15) | no | Abreviatura |
| units.decimal_places | SMALLINT | no | 0; CHECK entre 0 y 3 |
| units.active | BOOLEAN | no | true |
| units.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |

La pareja `subcategories(id, category_id)` es UNIQUE para permitir la FK compuesta de productos. Los catálogos se desactivan conservando relaciones. La validación del servicio impide asignar referencias inactivas nuevas, desactivar catálogos con productos activos y modificar la precisión de unidades referenciadas; estas reglas de estado entre filas se coordinan en el servicio, no se expresan como CHECK que consulte otras tablas.

## Productos y saldo de base

| Tabla/campo | Tipo | Nulo | Restricción / finalidad |
| --- | --- | --- | --- |
| products.id | BIGINT PK | no | Generado |
| products.sku | VARCHAR(50) | no | Único normalizado, código interno |
| products.barcode | VARCHAR(80) | sí | Único sobre lower(btrim(barcode)) cuando no es NULL; CHECK no vacío |
| products.name | VARCHAR(180) | no | Nombre; índice simple de consulta, permite productos con el mismo nombre |
| products.description | TEXT | sí | Descripción |
| products.category_id | BIGINT FK | no | categories.id, RESTRICT |
| products.subcategory_id | BIGINT | sí | Parte de FK compuesta; índice |
| products.brand_id | BIGINT FK | sí | brands.id, RESTRICT |
| products.unit_id | BIGINT FK | no | units.id, RESTRICT |
| products.presentation | VARCHAR(180) | sí | Texto, no conversión |
| products.sale_price | NUMERIC(14,2) | no | 0; no negativo |
| products.reference_cost | NUMERIC(14,2) | no | 0; no negativo, lectura por permiso |
| products.minimum_stock | NUMERIC(14,3) | no | 0; no negativo |
| products.location | VARCHAR(255) | sí | Ubicación física |
| products.active | BOOLEAN | no | true |
| products.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |
| inventory_balances.id | BIGINT PK | no | Generado |
| inventory_balances.product_id | BIGINT FK | no | products.id, RESTRICT |
| inventory_balances.warehouse_id | BIGINT FK | no | warehouses.id, RESTRICT |
| inventory_balances.quantity | NUMERIC(14,3) | no | 0; CHECK quantity >= 0 |
| inventory_balances.created_at / updated_at | TIMESTAMPTZ | sí | Marcas Laravel |

La FK `(products.subcategory_id, products.category_id) → subcategories(id, category_id)` rechaza combinaciones incompatibles aun mediante SQL directo; al ser opcional la subcategoría, `NULL` permite un producto sin ella. Existe índice `(category_id, active)` para filtros. La combinación `(product_id, warehouse_id)` de saldos es UNIQUE.

El producto y su saldo cero se crean en la misma transacción del servicio o del seeder. El formulario no acepta stock. No existe movimiento de inventario por esa inicialización. Los importes futuros usarán `NUMERIC(16,2)`; ninguna tabla comercial se crea anticipadamente. El máximo de cantidades es 99,999,999,999.999; precio/costo, 999,999,999,999.99. Los valores se validan como cadenas antes de persistir y las cantidades respetan `units.decimal_places`.

## Auditoría

| Campo audit_events | Tipo | Nulo | Restricción / finalidad |
| --- | --- | --- | --- |
| id | BIGINT PK | no | Generado |
| actor_id | BIGINT FK | sí | users.id, RESTRICT; admite inicialización sin actor |
| action | VARCHAR(100) | no | Acción explícita |
| entity_type | VARCHAR(100) | no | Clase/morph del modelo registrado |
| entity_id | BIGINT | sí | Identificador del modelo |
| before_values | JSONB | no | `{}`; solo allowlist del servicio |
| after_values | JSONB | no | `{}`; solo allowlist del servicio |
| reason | TEXT | sí | Motivo explícito cuando corresponde |
| occurred_at | TIMESTAMPTZ | no | CURRENT_TIMESTAMP; servicio usa UTC |

Índices `(actor_id, occurred_at)`, `(entity_type, entity_id)` y `(action, occurred_at)`. No hay `created_at/updated_at`: cada evento es un registro de instante. `entity_type/entity_id` identifican modelos de varias tablas y no forman una FK polimórfica inexistente en PostgreSQL. Los modelos auditables no tienen endpoints de borrado; el actor sí tiene una FK real. La allowlist del servicio excluye contraseñas, hashes, tokens, cookies, claves y cuerpos completos de solicitud. Los eventos se insertan en la conexión y transacción del modelo modificado; un fallo de auditoría revierte la operación llamadora.

## Datos iniciales y evolución

`SystemSeeder` crea 6 roles y 22 permisos, organización única y unidades `UND` (0 decimales), `KG` (3) y `LT` (3). Cada registro se crea si falta; no restaura preferencias ni permisos revocados de roles existentes. Solo un rol recién creado recibe sus permisos por defecto. No crea usuarios ni contraseñas.

`DemoSeeder`, permitido únicamente en `local/testing`, crea categorías y marca ficticias y 5 productos: con y sin marca/subcategoría/barcode, entero/fraccionario y activo/inactivo. Todos reciben exclusivamente saldo cero y las repeticiones conservan precios, estados y cantidades existentes. `PRODUCTO-CONCURRENCIA` permanece en cero; la unidad positiva de la futura prueba corresponde al servicio de inventario de Yourguen.

Toda evolución futura se agrega mediante una migración nueva una vez compartida/aplicada esta inicial. Las actualizaciones habituales ejecutan `migrate`, nunca reconstruyen la base. Yourguen deberá crear movimientos y orígenes referenciales, habilitar el control de historial antes de cualquier cambio de unidad y conservar esta base de autenticación, catálogos y saldos.

## Collation Unicode incremental

La segunda migración cambia la collation de nombres/códigos únicos de branches, warehouses, roles, categories, brands y units, email de users, nombre de subcategories y sku/barcode/name de products a pg_catalog.pg_c_utf8 (PostgreSQL17/UTF8). Conserva longitudes, tipos, filas, FK e índices; PostgreSQL reconstruye los índices dependientes. Se detectan duplicados equivalentes antes del cambio y la migración aborta sin fusionar datos si existen. Mayúsculas/minúsculas con tildes se comparan coherentemente; letras con y sin tilde permanecen distintas.

## Actualización MANUEL-02: cuentas y permisos individuales

La migración000003 añade users.is_demo BOOLEAN NOT NULL DEFAULT false; cuentas previas conservan su contraseña, cargo y condición normal. Normaliza email a lower(btrim(email)) con pg_c_utf8 y añade CHECK users_email_lowercase_check. Un INSERT/UPDATE SQL con correo no normalizado se rechaza con 23514; la unicidad del correo normalizado sigue vigente. La aplicación normaliza la entrada antes de guardar o autenticar.

La migración000004 crea user_permissions: user_id BIGINT NOT NULL FK users.id RESTRICT; permission_id BIGINT NOT NULL FK permissions.id RESTRICT; allowed BOOLEAN NOT NULL; PRIMARY KEY(user_id,permission_id). Sin fila: herencia del cargo. allowed=true concede la excepción; false la deniega. No hay timestamps ni eliminaciones en cascada. La auditoría guarda permission_codes antes/después, sin contraseñas.

Roles agregados: supervisor (lectura de catálogos/costos/configuración) y warehouse (Bodega: lectura sin costos). Se conservan admin, purchasing, seller, viewer y personalizaciones existentes. DemoUserService crea explícitamente cuatro usuarios is_demo solo en entorno/conexión permitida; los seeders habituales siguen sin crear cuentas. Las plantillas y sus excepciones no dependen del texto del correo.
