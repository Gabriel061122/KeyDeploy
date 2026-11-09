# Especificación propuesta: capa de servicios

**Estado:** acordada para esta entrega según la instrucción del usuario.

## Contexto actual

La aplicación Laravel está en `proyecto_laravel/`. Actualmente dispone de modelos y migraciones para usuarios, regiones, productos, proyectos, requerimientos, arquitecturas y elementos de arquitectura. La lógica compartida existente está en `RequirementRules` y `ArchitectureValidator`; los controladores de proyectos, requerimientos y arquitecturas también realizan parte de la coordinación y persistencia.

Los requisitos funcionales describen además propuestas IA, validación técnica, carritos, presupuestos, pedidos, pagos simulados, despliegues y auditoría. No todos esos dominios tienen todavía modelos y tablas implementados.

## Objetivo

Implementar servicios de aplicación que concentren las reglas de negocio de los dominios ya implementados y persistidos: proyectos, requerimientos y arquitecturas manuales. Los controladores existentes delegarán en estos servicios y no duplicarán sus operaciones de dominio.

## Alcance

1. **Proyectos:** crear, actualizar, eliminar lógicamente y restaurar; la propiedad siempre se determina desde el usuario autenticado, y se conservan las relaciones al borrar/restaurar.
2. **Requerimientos:** crear, actualizar y eliminar vinculados al proyecto autorizado; `project_id` se resuelve desde la ruta y `source=manual` lo fija el servidor; se conserva la matriz tipo/operador y normalización de `RequirementRules`.
3. **Arquitecturas manuales:** validar región/productos/cantidades/configuraciones con `ArchitectureValidator`, y guardar arquitectura y elementos en una única transacción; preservar el borrador de sesión y el flujo HTTP existente.
4. Reutilizar las rutas, controladores, Form Requests, políticas, modelos, migraciones, vistas y resultados HTTP existentes. Los controladores delegan la ejecución de operaciones en los nuevos servicios; no crear una interfaz, endpoint o funcionalidad nueva.
5. No confiar en identificadores de propietario, proyecto, región o procedencia enviados sin validación desde el cliente. Los servicios reciben entidades/datos ya validados o verifican los invariantes de dominio antes de persistir.

## Exclusiones explícitas de esta entrega

- No implementar **ninguna** funcionalidad, servicio, regla de negocio, ruta o modelo para IA/generación de propuestas.
- No implementar presupuestos ni su generación, con o sin IA; tampoco carrito, pedidos o pagos.
- No implementar catálogo administrativo, despliegues, cargos, soporte, tráfico simulado ni auditoría.
- No añadir migraciones, modelos o dependencias; el alcance queda limitado al esquema existente.

Los acuerdos recientes sobre precios recurrentes, vigencia de presupuestos, tráfico y ciclo de vida de despliegues quedan fuera de esta entrega; no deben provocar cambios ni servicios en esos dominios.

## Reglas de comportamiento que no deben cambiar

- Los proyectos pertenecen al usuario autenticado; no se confía en un `user_id` enviado por el cliente.
- Los requerimientos se guardan vinculados al proyecto resuelto por la ruta y el usuario no puede falsificar `source`.
- Los productos de arquitecturas deben existir, estar activos y estar disponibles en la región; una arquitectura inválida no deja persistencia parcial.
- El borrado lógico de proyectos conserva sus requerimientos y arquitecturas y permite la restauración autorizada.
- Las arquitecturas solo admiten productos existentes, activos y disponibles en la región seleccionada; se respetan las restricciones existentes de elementos, tipos, cantidades y JSON.
- Las operaciones compuestas de una arquitectura son atómicas: si falla un elemento, no queda una arquitectura ni elementos parciales.
- Se conservan el comportamiento observable y las pruebas existentes, excepto si una prueba contradice los requisitos documentados; cualquier conflicto se resuelve en esta especificación antes de alterar el comportamiento.

## Criterios de aceptación propuestos

- Las reglas de los tres dominios incluidos se ejecutan a través de servicios inyectables y los controladores no duplican las operaciones delegadas.
- Se preservan autorización, validación HTTP, rutas, resultados visibles y restricciones actuales.
- Las pruebas existentes pertinentes pasan y las nuevas pruebas cubren reglas/invariantes de la especificación, incluidos los casos de fallo atómico de arquitectura.
- No se agregan modelos, migraciones, rutas, dependencias ni comportamiento de los dominios expresamente excluidos.
