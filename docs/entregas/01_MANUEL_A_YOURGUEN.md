# 01 · Manuel a Yourguen

Fecha: 19 de octubre de 2026, America/Guatemala. Referencia local MANUEL-01. Rama `codex/manuel-base-acceso-catalogos`. Árbol de trabajo sin commit; sin PR ni publicación. Esta es la entrega técnica de la fase base; recepción por otro integrante pendiente.

## Qué recibe

Laravel 13.37.0/PHP 8.4.12 y PostgreSQL 17.11: acceso por sesión, cuatro roles y 22 permisos, cuentas, configuración única y catálogos funcionales. Cinco productos genéricos reproducibles, todos con saldo cero. Validaciones, restricciones PostgreSQL, auditoría atómica, decimales BCMath y recursos Bootstrap/Vite locales. README, locks, dos migraciones, seeders, matriz y evidencia sanitizada.

El PHP de XAMPP no se sustituyó. En esta PC se utiliza PHP 8.4 de Laragon y un ini privado. PostgreSQL17 portable en puerto55432 no cambia la instalación16 existente. Runtime y credenciales no se comparten por Git.

## Pasos de recepción

Siga el apartado «Instalación desde cero» del README. Prepare PostgreSQL17 y sus bases/esquemas, configure .env y .env.testing con su contraseña, instale con composer install y npm ci, genere APP_KEY solo en su entorno nuevo, ejecute migrate, SystemSeeder y opcionalmente DemoSeeder. Cree su administrador con `php artisan sicovi:crear-administrador`, compile y arranque en http://127.0.0.1:8000/login. Use `scripts/php.ps1` en Windows si necesita el ini privado.

Después ejecute `php vendor/bin/phpunit --colors=never` con su PHP compatible y la configuración testing separada. La guarda efectiva debe mostrar sicovi_test/sicovi; no la omita. Ejecute el diagnóstico de solo lectura `php scripts/diagnostico.php`. Verifique que hay cinco productos/saldos cero y que los roles deniegan accesos indebidos.

No copie la contraseña ni APP_KEY de Manuel. No actualice con migrate:fresh. SystemSeeder es repetible y conserva permisos/preferencias; DemoSeeder no restaura registros editados. La migración Unicode detecta duplicados existentes antes de cambiar índices.

## Continuidad de Yourguen

Implementar proveedores, compras/recepción, entradas, ajustes, existencias operativas, kardex y movimientos. Añadir permisos comerciales y su servicio de inventario. Leer CONTINUIDAD_MODULOS antes de diseñar escrituras: transacción coordinada, bloqueos de saldos en orden estable, idempotencia y movimientos con origen verificable.

Antes de habilitar los primeros movimientos, impedir cambios de unidad de productos con historial; la fase base solo valida precisión y saldo vigente. Un saldo cero inicial no es una entrada. No cargar stock positivo directamente para simular compras. PRODUCTO-A tiene precio25.00/costo15.00; el recorrido100→120→115 y anulaciones/reintentos NO está implementado ni probado en esta fase.

Kevin conserva clientes/ventas y Luis anulaciones/panel/reportes/integración. No hay controladores comerciales vacíos ni respuestas simuladas. No se implementaron clientes o proveedores.

## Verificación local y límites

Suite y cifras definitivas en `docs/evidencias/manuel/phpunit.txt`; concurrencia administrativa real en `concurrencia-administrador.txt`. La prueba de dos procesos conserva un administrador activo y una sola desactivación. La instalación local tiene una organización, cuatro roles, 22 permisos y cinco productos/saldos cero, sin cuenta administrativa universal. Cuenta QA aislada en sicovi_verificacion, sin credenciales en la entrega.

Revisión visual en navegador: inicio, login, listado1366×768 y formulario/menú360×800 mediante iframes exactos presentados75%. No equivale a prueba física de todos los dispositivos. Compilación frontend, rutas, plataforma y sintaxis Blade verificadas; no hay despliegue.

Pendiente: instalación/revisión en otra computadora, aceptación del receptor, restauración cronometrada y pruebas integradas comerciales. Registrar responsable, fecha, comando y evidencia cuando ocurran. No se atribuye una aprobación a Yourguen ni se declara listo el proyecto completo para producción.

## Complemento MANUEL-02

La base evolucionó a seis cargos y cuatro migraciones. Incorpora correo minúsculo exigido por PostgreSQL, usuarios demo locales etiquetados y casillas con defaults por cargo y diferencias individuales. Actualice con migrate --force, SystemSeeder y build; no regenere claves ni reconstruya desarrollo. Las cuentas demo son opcionales y están documentadas en CARGOS_Y_ACCESOS; no son cuentas de producción.

Yourguen debe usar hasPermission/Gates/Policies, que incorporan user_permissions. Leer solo el rol omitiría excepciones. Permisos nuevos y defaults de módulos futuros requieren evolución explícita; no sobrescribir personalizaciones. Las cuentas normales conservan contraseñas fuertes. Las pruebas actuales complementan las120 pruebas históricas; consultar roles_usuarios para el cierre nuevo.
