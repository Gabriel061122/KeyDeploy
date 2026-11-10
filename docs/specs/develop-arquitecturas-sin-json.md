# Especificación: develop sin configuración JSON y formulario de requerimientos

- **Estado:** propuesta de trabajo (instrucción del usuario: aplicar en `develop` los arreglos de la rama EP04)
- **Base:** rama `develop` (ad539e0), que incluye precios por proveedor, presupuestos y reglas de negocio
- **Ámbito:** arquitecturas manuales (RF05/RN04/RN05) y formulario de requerimientos (RF03)
- **Fecha:** 2026-11-10

## 1. Problema

En `develop` el CLIENTE sigue teniendo que escribir a mano un JSON por elemento de la
arquitectura. Ese JSON cumple hoy tres funciones internas:

1. declarar el consumo mensual de los productos con tarifa por hora o por GB
   (`hours_per_month`, `gb_per_month`);
2. satisfacer los requerimientos obligatorios del proyecto (claves como `vcpu` o `ram_gb`);
3. rellenar la columna «Configuración» de previsualización, ficha y presupuesto.

Además arrastra los defectos ya corregidos en la otra rama:

- el formulario de arquitecturas exige rellenar los 6 huecos aunque diga «al menos 3»;
- cambiar de región pierde lo tecleado;
- una región sin productos deja enviar el formulario;
- el formulario de requerimientos no envía `data_type`, por lo que crear y editar
  requerimientos falla con «El tipo de dato es obligatorio», y cambiar de tipo pierde lo
  tecleado.

**Objetivo:** que el CLIENTE nunca escriba JSON. La configuración se sustituye por un campo
numérico guiado de consumo mensual, y los requerimientos se comprueban contra los atributos
del catálogo. Se corrigen además los cuatro defectos de formulario.

## 2. Decisiones de diseño

### 2.1 Consumo mensual guiado (sustituye al JSON)

- Cada elemento del formulario tiene un campo numérico opcional **«Consumo mensual»**
  (`items[*][usage]`), decimal no negativo con hasta 2 decimales.
- El servidor interpreta ese valor según la modalidad de la tarifa del producto elegido:
  - `fijo_mensual`: se ignora (se guarda sin consumo).
  - `por_hora`: se guarda como `hours_per_month`.
  - `por_gb`: se guarda como `gb_per_month`.
- Si el producto es por hora o por GB y no se indica consumo (> 0), el error identifica el
  elemento y la unidad: «El elemento 3 (VM GPU) se factura por hora: indica el consumo mensual
  en horas.»
- La columna «Configuración» desaparece de previsualización, ficha y presupuesto. La
  información de consumo ya está en la columna «Consumo mensual» calculada por precios.
- `architecture_items.config` se conserva en base de datos, pero solo con las claves internas
  `hours_per_month` / `gb_per_month` cuando proceda. El cliente no puede enviar JSON ni claves
  arbitrarias: si llega `items[*][config]` en la petición, se ignora.

### 2.2 Requerimientos contra atributos del catálogo

- La comprobación de requerimientos obligatorios deja de leer la configuración del elemento y
  pasa a leer los **atributos del producto** (`products.config`: `vcpu`, `ram_gb`,
  `capacity_gb`, `storage_type`, `engine`, `bandwidth_mbps`, `rules_count`,
  `retention_days`…).
- Un requerimiento obligatorio se satisface si **algún producto seleccionado** en la
  arquitectura declara la clave y su valor cumple el operador. La cantidad no altera el valor
  del atributo.
- Si ningún producto declara la clave, el error lo dice citando el atributo del catálogo.
  Si la declaran pero ninguno cumple, se indica que la arquitectura no lo satisface.
- Los requerimientos opcionales no bloquean (sin cambios).
- Esto elimina la posibilidad de que un POST manipulado se invente atributos para saltarse
  RN04.

## 3. Alcance

### Incluido

**Arquitecturas**
- Quitar el textarea de configuración JSON del formulario de alta.
- Añadir el campo guiado de consumo mensual.
- Construir `config` en servidor a partir del consumo y la modalidad.
- Validar el consumo según la modalidad del producto.
- Descartar huecos vacíos; mínimo 3 elementos y 2 tipos; fila parcial con error que cita el
  hueco visible.
- Bloquear el envío si la región no tiene productos activos y disponibles, con aviso; rechazo
  claro si llega una petición directa.
- Recargar el catálogo al cambiar de región conservando nombre, productos, cantidades y
  consumo (formulario único + acción de recarga por POST con entrada antigua).
- Quitar la columna «Configuración» de previsualización, ficha y presupuesto.

**Requerimientos**
- Formulario único con `data_type` en el envío principal (POST alta, PUT edición).
- Botón «Actualizar» con `formaction` a una ruta de recarga y `formnovalidate`; la recarga
  devuelve al formulario con entrada antigua.
- Rutas: `POST proyectos/{project}/requerimientos/tipo` (`projects.requirements.refresh`) y
  `PUT proyectos/{project}/requerimientos/{requirement}/tipo`
  (`projects.requirements.refresh.edit`).

### Excluido

- Cambiar precios, tarifas, modalidades o el catálogo.
- Rediseñar la emisión de presupuestos o sus snapshots (solo dejan de mostrar configuración).
- Traducir mensajes en inglés de registro/403.
- Edición o borrado de arquitecturas (no existen).
- Añadir JavaScript.

## 4. Contrato de entrada de la arquitectura

| Campo | Reglas |
|---|---|
| `name` | obligatorio, máx. 150 |
| `region_id` | obligatorio, existente y **igual a la región del proyecto** |
| `items` | array; tras descartar huecos, entre 3 y 6 elementos |
| `items[*][product_id]` | obligatorio en filas no vacías, existente |
| `items[*][quantity]` | entero 1..99999 |
| `items[*][usage]` | opcional, numérico >= 0, <= 999999999.99, hasta 2 decimales |
| `items[*][config]` | ignorado siempre |

- Producto activo y disponible en la región (RN05).
- Mínimo 3 elementos y 2 tipos distintos.
- Consumo obligatorio (> 0) para modalidades por hora y por GB.
- Hueco totalmente vacío (sin producto ni cantidad): se descarta sin errores.
- Fila parcial (producto sin cantidad o cantidad sin producto): error con el número de hueco
  visible.

## 5. Persistencia

- `architecture_items.config`: `{}` para tarifa fija; `{"hours_per_month": "<valor>"}` o
  `{"gb_per_month": "<valor>"}` para las demás. Valores normalizados como número JSON.
- El presupuesto congela en sus líneas la misma configuración interna.
- Sin cambios de esquema.

## 6. Criterios de aceptación

1. El formulario de alta no contiene ningún campo ni texto que mencione JSON ni «configuración
   JSON»; sí contiene un campo «Consumo mensual» por elemento.
2. Alta con 3 elementos rellenos y 3 huecos vacíos: válida (con consumo cuando la modalidad lo
   exija).
3. Alta con 6 elementos: válida.
4. Huecos vacíos no generan errores; una fila parcial sí, citando el hueco visible.
5. Un producto por hora sin consumo produce un error que cita el elemento y la unidad; con
   consumo produce el precio correcto.
6. Un producto por GB se comporta igual con GB.
7. Un producto de tarifa fija ignora el consumo enviado.
8. `items[*][config]` con JSON válido, inválido o ausente no altera el resultado ni provoca
   errores.
9. Un requerimiento obligatorio se satisface con atributos del catálogo (`vcpu` >= N, etc.) y
   bloquea cuando ningún producto los cumple o la clave no existe; los opcionales no bloquean.
10. La previsualización, la ficha y el presupuesto no muestran columna de configuración.
11. Región sin productos: aviso claro y sin botón de revisión; petición directa rechazada con
    mensaje comprensible.
12. «Actualizar» región conserva nombre, productos, cantidades y consumo, y no guarda nada.
13. Requerimientos: el formulario de alta y edición incluye `data_type`; guardar crea y
    actualiza; «Actualizar» conserva campo, tipo, operador, valor y obligatoriedad sin guardar.
14. Las reglas de `RequirementRules` y los permisos no cambian.
15. La suite completa de `develop` pasa, incluidos precios, presupuestos y reglas de negocio.

## 7. Supuestos

- Se mantienen los 6 huecos con mínimo 3.
- El consumo mensual es un único campo por elemento; su unidad depende del producto elegido y
  se explica en la ayuda y en los errores.
- `products.config` es la fuente de verdad de los atributos; no se añaden atributos nuevos.
- Las pruebas que hoy declaran configuración JSON se adaptan al nuevo contrato.
