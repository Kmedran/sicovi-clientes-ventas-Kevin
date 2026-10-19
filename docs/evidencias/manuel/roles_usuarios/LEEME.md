# Evidencia final MANUEL-02

Fecha: 19/10/2026, America/Guatemala. Rama codex/manuel-base-acceso-catalogos, sin commit ni publicación. Actualiza la entrega inicial con seis cargos, correo minúsculo y permisos personalizados por persona.

- phpunit.txt: **168 pruebas / 1,019 aserciones correctas**,47.655 s, PHP 8.4.12, PHPUnit 12.5.40, PostgreSQL 17.11 en sicovi_test/sicovi. Incluye regresiones previas y nuevas pruebas.
- cuentas-demo.txt: creación local explícita de cuatro cuentas solicitadas, sin contraseñas en la salida. El código y la guía identifican sus claves como fixtures públicos locales.
- postgresql-local.json: base efectiva sicovi_local/sicovi, seis roles, 22 permisos, cuatro usuarios, cinco productos/saldos cero y cuatro eventos de creación. No contiene hashes, secretos o cadenas de conexión.
- concurrencia-administrador.txt: dos procesos/conexiones reales, contención observada, un administrador activo y una sola desactivación auditada en sicovi_test.
- vendedor-permisos.png: formulario real con cargo Vendedor y explicación de personalización individual, viewport móvil de la ventana disponible.
- casillas-vendedor.png: acciones de consulta marcadas y administración sin marcar; selección restaurada a los defaults del vendedor.
- casillas-personalizadas-sin-guardar.png: revisión de agregar/quitar en el formulario, antes de guardar; no se creó otro usuario ni se alteraron los defaults de las cuatro cuentas reales durante esa prueba visual.

La revisión de navegador autenticó adminm@gmail.com y comprobó5 acciones de Vendedor,22 de Administrador, el cambio de casillas y el botón de restaurar. La persistencia de las diferencias y la independencia de otros vendedores están verificadas en UserPermissionsTest. Las otras tres credenciales pasaron el login HTTP con persistencia PostgreSQL en DemoAccountsTest.

Las cuentas demo solo se habilitan por bandera/env/conexión efectiva permitida; las cuentas normales conservan contraseñas fuertes. Los correos son identificadores SICOVI; no se crearon buzones Gmail ni se enviaron mensajes. Los módulos comerciales futuros no se implementaron. Pendientes externos de la entrega inicial continúan abiertos.
