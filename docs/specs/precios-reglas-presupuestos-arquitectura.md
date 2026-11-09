# Especificación: precios, reglas y presupuestos desde arquitecturas manuales

**Estado:** aprobada por el usuario; contrato funcional para la implementación.

## 1. Objetivo y contexto

Completar el cálculo de precios, las reglas de negocio de arquitecturas manuales y la emisión de presupuestos a partir de una arquitectura guardada, sin depender del carrito ni de funcionalidades de IA.

La implementación toma como base funcional `develop`, que ya integra el catálogo por disponibilidad regional y el trabajo de carrito/presupuestos. La rama actualmente seleccionada, `feature/service-layer-modeled-domains`, es anterior a parte de esa integración.

### Estado encontrado en `develop`

- `PricingModel` contempla `fijo_mensual`, `por_hora` y `por_gb`; `ProductAvailability` mantiene precio y modalidad por región.
- `ArchitectureValidator` ya rechaza arquitecturas con menos de 3 elementos, menos de 2 tipos, productos inexistentes/inactivos/no disponibles y cantidades o configuraciones inválidas.
- `QuoteService` ya genera desde `CartItem` un presupuesto con líneas históricas y transacción, pero también vacía el carrito. Su interpretación actual (`mensual` frente a cualquier otro valor como pago único) no coincide con los valores del enum de catálogo (`fijo_mensual`, `por_hora`, `por_gb`).
- Las pruebas de cálculo existentes usan `mensual`/`unico`; por tanto, no prueban los tres modelos vigentes del catálogo. La prueba que describe un aviso por un único tipo tampoco comprueba el aviso.

La implementación no cambia ese flujo del carrito ni sus clases, rutas, vistas, migraciones o pruebas. Añade una entrada independiente desde una arquitectura manual y reutiliza el almacenamiento de presupuestos ya existente cuando sea posible.

## 2. Alcance

1. Calcular en el servidor el coste de una arquitectura manual con tarifas regionales del catálogo.
2. Mostrar un desglose calculado al revisar y consultar la arquitectura.
3. Aplicar cinco reglas explícitas: bloqueos técnicos/funcionales y un aviso no bloqueante por presupuesto.
4. Emitir y consultar un presupuesto desde una arquitectura manual, guardando una instantánea inmutable dentro de una transacción.
5. Mantener las tres funcionalidades en ramas independientes, sin crear una rama de “capa de servicios”.

## 3. Exclusiones

- No modificar ni consumir `CartItem`; no vaciar, editar ni cambiar rutas o pantallas del carrito.
- No modificar `QuoteService::emitFromCart` ni el endpoint `quotes.emit` existente.
- No incluir IA, generación automática de arquitecturas, pedidos, pagos, despliegues ni caducidad de presupuestos.
- No aceptar precios ni totales oficiales enviados por el navegador.
- No implementar el CRUD administrativo del catálogo. Para los productos existentes, los seeders inicializan el nuevo dato de coste único en cero céntimos; cualquier cargo futuro deberá declararse explícitamente en los datos del catálogo, sin añadir una pantalla de administración.

## 4. Cálculo de precios acordado

### 4.1 Tarifas y fórmulas

Se reutiliza el enum de catálogo existente. `price_cents` representa la tarifa regional por unidad facturable:

| Modalidad | Entrada de uso en `ArchitectureItem.config` | Importe recurrente mensual por línea |
|---|---|---|
| `fijo_mensual` | Ninguna | `price_cents × quantity` |
| `por_hora` | `hours_per_month` | `price_cents × hours_per_month × quantity` |
| `por_gb` | `gb_per_month` | `price_cents × gb_per_month × quantity` |

Se añade `one_time_price_cents` a la disponibilidad regional. Este cargo único puede coexistir con cualquiera de las tres tarifas y se calcula como `one_time_price_cents × quantity`. Así se distinguen el subtotal mensual y el pago único sin inventar una cuarta modalidad recurrente.

Los campos `hours_per_month` y `gb_per_month` se obtienen de la configuración de cada elemento de arquitectura, no de campos de precio ocultos. Para `por_hora` y `por_gb` son obligatorios; deben ser números no negativos con hasta dos decimales. La tarifa fija no requiere esos campos. Un valor cero es válido y produce coste cero para ese consumo.

### 4.2 Dinero y precisión

- Los precios y los subtotales se representan en céntimos enteros y la interfaz los presenta en euros.
- Cuando el uso fraccionario produce una fracción de céntimo, se redondea una vez por línea al céntimo más próximo, con empate hacia arriba; después se suman las líneas en céntimos.
- El coste inicial mostrado es `subtotal mensual de un mes + subtotal único`.
- Toda tarifa se vuelve a leer del catálogo en el servidor al calcular; no se confía en totales o precios del formulario.
- Una disponibilidad sin tarifa válida, un valor de uso ausente o un cálculo fuera del rango representable se rechaza con un mensaje concreto; no se persiste un presupuesto parcial.

### 4.3 Resultado visible

La revisión y el detalle de una arquitectura muestran, por elemento, producto, región, modalidad, cantidad, consumo mensual si aplica, subtotal mensual y cargo único. El resumen separa subtotal mensual, pago único y coste inicial. Los cambios en cantidad, consumo o tarifa causan un recálculo del lado servidor.

## 5. Cinco reglas de negocio acordadas

Las reglas se evalúan al revisar/guardar una arquitectura y de nuevo antes de emitir un presupuesto. Un resultado contendrá errores bloqueantes y avisos separados.

| # | Regla acordada | Resultado |
|---|---|---|
| 1 | La arquitectura debe contener al menos 3 elementos. | Bloquea con motivo visible. Ya existe validación equivalente. |
| 2 | La arquitectura debe contener al menos 2 tipos de producto distintos. | Bloquea con motivo visible. Ya existe validación equivalente. |
| 3 | La región de la arquitectura debe ser la región requerida por el proyecto y cada producto debe existir, estar activo y estar disponible en esa región. | Bloquea con el producto/región causante. La disponibilidad ya se valida; se acuerda comprobar además la igualdad con la región del proyecto. |
| 4 | Cada requisito obligatorio del proyecto debe satisfacerse con la configuración de la arquitectura. | Bloquea si falta el dato o no cumple el operador/valor. |
| 5 | Si el coste inicial supera `Project.max_budget`, se muestra un aviso y se permite guardar/emitir el presupuesto. | Aviso no bloqueante; muestra ambos importes y la diferencia. |

### 5.1 Evaluación de requerimientos (regla 4)

Contrato acordado para los campos configurables actuales:

- `Requirement.field_key` identifica una clave de primer nivel en la configuración JSON de un elemento de arquitectura.
- El valor se compara usando `Requirement.data_type` y `Requirement.operator`, conforme a `RequirementRules`.
- Si la clave aparece en más de un elemento, basta con que al menos un valor satisfaga el requisito. Un requisito obligatorio sin clave coincidente o sin ningún valor que lo satisfaga bloquea; un requisito opcional no bloquea.
- Las comparaciones numéricas son numéricas; los booleanos se normalizan con las convenciones existentes; las comparaciones de cadenas son sensibles a mayúsculas/minúsculas.

La asociación genérica entre `field_key` y claves de configuración no está formalizada en el modelo de catálogo actual. Se acuerda usar la coincidencia exacta con una clave de primer nivel para poder validar manualmente arquitecturas sin introducir IA ni un esquema específico por proveedor.

### 5.2 Interpretación acordada del presupuesto máximo

Se compara `max_budget` con el **coste inicial** (un mes recurrente más cargos únicos). Si se supera, el resultado es aviso, nunca error bloqueante, en concordancia con UT2.

## 6. Emisión y consulta de presupuesto desde arquitectura

- El CLIENTE autenticado solo puede emitir desde una arquitectura perteneciente a uno de sus proyectos no eliminados.
- Se añade una acción independiente desde el detalle de una arquitectura manual. No lee, modifica ni vacía el carrito.
- Antes de persistir se vuelven a validar región, estado/disponibilidad de productos, requisitos obligatorios y precios vigentes. Los bloqueos impiden emitir; el aviso de presupuesto permite continuar.
- Se reutilizan `quotes` y `quote_lines` existentes si el esquema integrado lo permite. Cada línea congela nombre, tipo, modalidad, cantidad, precio unitario, subtotal y configuración/consumo. Si un elemento tiene coste recurrente y único, se guardan dos líneas de snapshot para que ambos importes queden diferenciados sin alterar el formato de la tabla existente.
- La cabecera conserva proyecto, usuario, fecha de emisión y subtotales mensual, único y total inicial. No se define fecha de caducidad.
- Crear cabecera y líneas es una transacción única. Si falla cualquier escritura, no queda cabecera ni línea parcial.
- Cambios posteriores de tarifa, nombre, tipo o configuración del catálogo no cambian el presupuesto emitido.
- Tras emitir, la aplicación redirige al detalle del presupuesto existente. El aviso de exceso de presupuesto se muestra en el flujo de emisión; no altera el estado del presupuesto.

## 7. Validación, privacidad e integridad

- La autorización se resuelve a través del proyecto propietario; no se confía en `user_id`, `project_id`, región, precio o totales enviados por el cliente.
- La emisión se calcula con datos de BD y copia los valores históricos necesarios; las líneas del presupuesto no dependen de consultas posteriores al catálogo.
- Las validaciones fallidas conservan los datos editables útiles y no escriben arquitecturas o presupuestos parciales.
- Se mantienen CSRF, validación en servidor, escape Blade e integridad de claves foráneas existentes.

## 8. Criterios de aceptación verificables

1. Una tarifa `fijo_mensual` calcula exactamente precio × cantidad en el subtotal mensual.
2. Una tarifa `por_hora` requiere `hours_per_month` y calcula un ejemplo exacto en céntimos.
3. Una tarifa `por_gb` requiere `gb_per_month` y calcula un ejemplo exacto en céntimos.
4. El cargo único se conserva separado del mensual y el coste inicial suma ambos.
5. Consumos inválidos, tarifa ausente o configuración incompleta impiden emitir y no crean registros.
6. Se bloquean las reglas 1–4 con un motivo identificable y no se persisten cambios inválidos.
7. Una arquitectura por encima del presupuesto muestra el aviso y permite continuar.
8. No se puede usar una región distinta a la exigida por el proyecto ni un producto inactivo/no disponible.
9. La comprobación de los requisitos obligatorios cubre operadores de texto, numéricos y booleanos; un requisito opcional incumplido no bloquea.
10. Solo el propietario puede calcular/emitir/consultar el presupuesto de su arquitectura.
11. Emitir desde arquitectura guarda importes, fecha, líneas y configuraciones en una transacción; un fallo controlado revierte todo.
12. Modificar después el catálogo no altera el presupuesto histórico.
13. La funcionalidad nueva no cambia registros, contenido ni comportamiento del carrito y no invoca IA.

## 9. Ramas acordadas

Las ramas son por capacidad funcional, no por refactor técnico:

1. `feature/architecture-pricing`: cálculo por arquitectura, entrada de consumos, cargo único y desglose visible.
2. `feature/architecture-business-rules`: las cinco reglas, resultado de errores/avisos y validación de requisitos. Depende del cálculo para la regla de presupuesto.
3. `feature/architecture-quotes`: emisión desde arquitectura manual y snapshot transaccional. Depende de las dos anteriores y no modifica la emisión desde carrito.

Se parte de `develop`, donde están integrados el esquema regional de precios y los modelos actuales de presupuesto. Las ramas se abordan en ese orden por sus dependencias.

## 10. Decisiones aprobadas

1. Se usan las tres tarifas recurrentes (`fijo_mensual`, `por_hora`, `por_gb`) con consumos mensuales ingresados en la configuración de arquitectura.
2. Se añade `one_time_price_cents` como cargo único por unidad y disponibilidad regional, acumulable con la tarifa recurrente.
3. `max_budget` se compara con el coste inicial (subtotal mensual de un mes más cargos únicos); excederlo genera un aviso no bloqueante.
4. Un requisito se satisface si una clave exacta de configuración aparece en al menos un elemento y un valor cumple el operador. Las comparaciones de cadenas distinguen mayúsculas y minúsculas.
5. La emisión desde arquitectura es independiente y reutiliza el histórico existente sin modificar el flujo del carrito.
6. La región del proyecto es obligatoria para todos los productos de la arquitectura.
7. Los productos existentes en los seeders comienzan con un cargo único de cero céntimos; los cargos futuros deben declararse de forma explícita y no se inventan importes por defecto.
