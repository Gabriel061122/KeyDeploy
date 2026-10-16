# Casos de uso de KeyDeploy

## Alcance

Estos casos describen la versión de KeyDeploy definida en el análisis funcional: recomendación asistida por IA, validación y cálculo deterministas en la aplicación, catálogo de productos cloud, contratación simulada y seguimiento de despliegues simulados. No incluyen pagos reales ni aprovisionamiento de máquinas. El Servicio de IA es un actor externo; la aplicación y sus procesos internos no se modelan como actores.

## Resumen de casos de uso

| Código | Nombre | Actor principal | Estado |
|---|---|---|---|
| CU01 | Iniciar sesión | Usuario registrado | Revisado |
| CU02 | Crear Proyecto | CLIENTE | Revisado |
| CU03 | Definir requerimientos | CLIENTE | Revisado |
| CU04 | Generar propuesta de Arquitectura | CLIENTE | Revisado |
| CU05 | Validar propuesta | ARQUITECTO | Revisado |
| CU06 | Revisar y seleccionar Arquitectura | CLIENTE | Revisado |
| CU07 | Añadir productos al Carrito | CLIENTE | Revisado |
| CU08 | Emitir Presupuesto | CLIENTE | Revisado |
| CU09 | Contratar con pago simulado | CLIENTE | Revisado |
| CU10 | Gestionar Despliegue simulado | CLIENTE | Revisado |
| CU11 | Gestionar Catálogo | ADMINISTRADOR | Revisado |
| CU12 | Consultar auditoría | AUDITOR | Revisado |
| CU13 | Gestionar usuarios y roles | ADMINISTRADOR | Revisado |
| CU14 | Revisar propuestas de IA y decisiones | AUDITOR | Revisado |
| CU15 | Registrar cuenta de CLIENTE | Persona no registrada | Revisado |
| CU16 | Añadir o cancelar SOPORTE | CLIENTE | Revisado |

## CU01 — Iniciar sesión

| Campo | Descripción |
|---|---|
| **Código** | CU01 |
| **Nombre** | Iniciar sesión |
| **Actor principal** | Usuario registrado (CLIENTE, ARQUITECTO, AUDITOR o ADMINISTRADOR) |
| **Descripción** | Permite a un usuario acceder a KeyDeploy y utilizar las funciones autorizadas para su rol. |
| **Precondiciones** | El usuario tiene una cuenta activa y no mantiene una sesión iniciada. |
| **Flujo principal** | 1. El usuario solicita iniciar sesión.<br>2. Introduce sus credenciales.<br>3. La aplicación valida las credenciales y obtiene los roles asociados a la cuenta.<br>4. Si son válidas, inicia la sesión, registra el acceso según la política de auditoría y muestra las funciones permitidas.<br>5. Si no son válidas, informa del error sin revelar cuál de las credenciales era incorrecta. |
| **Resultado / postcondición** | La sesión queda iniciada con los permisos correspondientes o, si la autenticación falla, el usuario permanece sin sesión. |

## CU02 — Crear Proyecto

| Campo | Descripción |
|---|---|
| **Código** | CU02 |
| **Nombre** | Crear Proyecto |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite describir la aplicación que se desea construir y las condiciones que deberá respetar la infraestructura recomendada. |
| **Precondiciones** | El CLIENTE ha iniciado sesión. |
| **Flujo principal** | 1. El CLIENTE solicita crear un Proyecto.<br>2. Introduce nombre, descripción funcional, presupuesto máximo, número esperado de usuarios y región.<br>3. Puede añadir requisitos opcionales, por ejemplo, seguridad, copias de seguridad o necesidades de IA.<br>4. La aplicación valida los campos obligatorios —descripción, presupuesto, usuarios y región— y sus valores.<br>5. La aplicación guarda el Proyecto asociado al CLIENTE y lo muestra en su lista.<br>6. Si faltan datos obligatorios, señala los campos pendientes y no crea el Proyecto. |
| **Resultado / postcondición** | El Proyecto queda guardado y asociado a su propietario; ningún otro CLIENTE puede acceder a él. |

## CU03 — Definir requerimientos

| Campo | Descripción |
|---|---|
| **Código** | CU03 |
| **Nombre** | Definir requerimientos |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite transformar las necesidades descritas para un Proyecto en condiciones estructuradas que puedan revisarse y comprobarse. |
| **Precondiciones** | El CLIENTE ha iniciado sesión y dispone de un Proyecto propio con sus datos obligatorios completos. |
| **Flujo principal** | 1. El CLIENTE solicita estructurar los requerimientos del Proyecto.<br>2. La aplicación prepara la información mínima necesaria, sin identificadores de cuenta, y la envía al Servicio de IA.<br>3. El Servicio de IA devuelve campos, operadores, valores y, cuando proceda, si cada condición es obligatoria u opcional.<br>4. La aplicación comprueba el formato de la respuesta.<br>5. El CLIENTE revisa los requerimientos y los confirma o modifica.<br>6. La aplicación guarda los requerimientos confirmados asociados al Proyecto. |
| **Resultado / postcondición** | El Proyecto contiene requerimientos estructurados revisados por el CLIENTE. Si la respuesta externa no tiene el formato esperado, no se guarda como confirmada y se informa del error. |

## CU04 — Generar propuesta de Arquitectura

| Campo | Descripción |
|---|---|
| **Código** | CU04 |
| **Nombre** | Generar propuesta de Arquitectura |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite solicitar una o varias alternativas de infraestructura a partir de los requerimientos confirmados y los productos del Catálogo. |
| **Precondiciones** | El CLIENTE ha iniciado sesión, es propietario del Proyecto y este contiene requerimientos confirmados. |
| **Flujo principal** | 1. El CLIENTE solicita propuestas para su Proyecto.<br>2. La aplicación reúne los requerimientos y los datos pertinentes del Catálogo.<br>3. La aplicación envía esos datos al Servicio de IA, sin identificadores de cuenta ni acceso directo al Proyecto.<br>4. El Servicio de IA devuelve una o varias propuestas estructuradas con productos y configuraciones recomendados.<br>5. La aplicación valida que la respuesta tenga el formato esperado, registra las propuestas como borradores y calcula los precios a partir de las tarifas del Catálogo.<br>6. La aplicación presenta las propuestas, con sus recomendaciones, ventajas y desventajas, para su posterior revisión técnica. |
| **Resultado / postcondición** | Las propuestas quedan asociadas al Proyecto como borradores. Todavía no están aprobadas para aceptación ni contratación. |

## CU05 — Validar propuesta

| Campo | Descripción |
|---|---|
| **Código** | CU05 |
| **Nombre** | Validar propuesta |
| **Actor principal** | ARQUITECTO |
| **Descripción** | Permite comprobar la validez técnica de una propuesta de IA y dejar constancia de la revisión antes de que el CLIENTE pueda aceptarla. |
| **Precondiciones** | Existe una propuesta borrador para un Proyecto. El ARQUITECTO ha iniciado sesión con su rol. |
| **Flujo principal** | 1. El ARQUITECTO abre las propuestas pendientes de revisión.<br>2. La aplicación comprueba unidades, requisitos mínimos, disponibilidad en la región solicitada y compatibilidad entre productos.<br>3. Si una propuesta incumple reglas, la aplicación la marca como no válida y muestra el motivo.<br>4. El ARQUITECTO examina las propuestas que superaron las comprobaciones y, si procede, ajusta productos o configuraciones.<br>5. La aplicación vuelve a validar toda propuesta modificada.<br>6. El ARQUITECTO registra el resultado de la revisión técnica. |
| **Resultado / postcondición** | Las propuestas válidas quedan revisadas y disponibles para el CLIENTE. Las no válidas conservan los motivos de rechazo. El ARQUITECTO no puede editar una Arquitectura ya aceptada. |

## CU06 — Revisar y seleccionar Arquitectura

| Campo | Descripción |
|---|---|
| **Código** | CU06 |
| **Nombre** | Revisar y seleccionar Arquitectura |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite comparar las alternativas revisadas, ajustar opciones permitidas y aceptar la que mejor se adapte al Proyecto. |
| **Precondiciones** | El CLIENTE ha iniciado sesión, es propietario del Proyecto y existe al menos una propuesta válida y revisada técnicamente. |
| **Flujo principal** | 1. El CLIENTE compara las propuestas, sus configuraciones, precios, ventajas y desventajas.<br>2. Modifica, si lo desea, los valores de configuración permitidos.<br>3. La aplicación vuelve a validar cualquier cambio; si afecta a la revisión técnica, el ARQUITECTO revisa la versión modificada antes de su aceptación.<br>4. El CLIENTE selecciona y acepta una propuesta válida y revisada.<br>5. La aplicación asocia la Arquitectura al Proyecto y congela la versión aceptada. |
| **Resultado / postcondición** | El Proyecto tiene una Arquitectura aceptada e inmutable. Cualquier cambio posterior se tramita como una propuesta nueva y debe volver a validarse y revisarse. |

## CU07 — Añadir productos al Carrito

| Campo | Descripción |
|---|---|
| **Código** | CU07 |
| **Nombre** | Añadir productos al Carrito |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite preparar la contratación con líneas derivadas de una Arquitectura aceptada y consultar su coste estimado. |
| **Precondiciones** | El CLIENTE ha iniciado sesión y tiene una Arquitectura aceptada asociada a su Proyecto. |
| **Flujo principal** | 1. El CLIENTE añade al Carrito los productos de la Arquitectura.<br>2. La aplicación crea líneas con producto, configuración, cantidad y periodo de facturación.<br>3. El CLIENTE ajusta las cantidades o configuraciones permitidas y elimina las líneas que no desea contratar.<br>4. La aplicación comprueba disponibilidad, dependencias y compatibilidad entre las líneas.<br>5. La aplicación recalcula y muestra el coste estimado y el desglose de conceptos únicos y recurrentes. |
| **Resultado / postcondición** | El Carrito queda guardado con las líneas válidas y el total estimado actualizado. Las líneas incompatibles o no disponibles no pueden pasar al Presupuesto. |

## CU08 — Emitir Presupuesto

| Campo | Descripción |
|---|---|
| **Código** | CU08 |
| **Nombre** | Emitir Presupuesto |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite generar una oferta económica vigente con el detalle de los productos seleccionados y sus precios congelados. |
| **Precondiciones** | El CLIENTE ha iniciado sesión y su Carrito contiene al menos una línea válida. |
| **Flujo principal** | 1. El CLIENTE solicita emitir el Presupuesto.<br>2. La aplicación comprueba de nuevo la validez de las líneas y calcula los importes usando las tarifas actuales.<br>3. Guarda una copia de productos, configuraciones, precios unitarios, cantidades y periodicidades.<br>4. Presenta por separado los conceptos únicos y recurrentes, incluyendo SERVICIO_IA y SOPORTE si se han seleccionado; no calcula impuestos.<br>5. Fija la fecha de caducidad del Presupuesto.<br>6. Si el total supera el presupuesto máximo del Proyecto, muestra una advertencia y alternativas más económicas disponibles, sin bloquear la emisión. |
| **Resultado / postcondición** | El Presupuesto queda emitido con su desglose, precios congelados y fecha de caducidad. Los cambios posteriores del Catálogo no modifican este registro. |

## CU09 — Contratar con pago simulado

| Campo | Descripción |
|---|---|
| **Código** | CU09 |
| **Nombre** | Contratar con pago simulado |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite aceptar un Presupuesto vigente y registrar una contratación de prueba, sin procesar un pago real. |
| **Precondiciones** | El CLIENTE ha iniciado sesión y dispone de un Presupuesto propio emitido que no ha caducado. |
| **Flujo principal** | 1. El CLIENTE solicita contratar el Presupuesto.<br>2. La aplicación comprueba que sigue vigente y crea un Pedido con las líneas y precios congelados.<br>3. El CLIENTE confirma el pago inicial simulado, que cubre el primer periodo mensual y los conceptos únicos.<br>4. La aplicación registra el pago simulado y crea el Despliegue simulado como una única operación lógica.<br>5. Si el pago o la creación no se completan, la aplicación revierte el estado parcial y comunica el resultado. |
| **Resultado / postcondición** | En caso de éxito, el Pedido queda pagado de forma simulada y se crea el Despliegue. No se almacena información bancaria ni se efectúa un cobro real. |

## CU10 — Gestionar Despliegue simulado

| Campo | Descripción |
|---|---|
| **Código** | CU10 |
| **Nombre** | Gestionar Despliegue simulado |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite consultar los recursos y el estado de un Despliegue simulado, ver su tráfico ficticio y gestionar las opciones disponibles. |
| **Precondiciones** | El CLIENTE ha iniciado sesión y tiene un Despliegue propio asociado a un Pedido pagado. |
| **Flujo principal** | 1. El CLIENTE consulta el estado, recursos y cargos del Despliegue.<br>2. Mientras el Despliegue esté activo, la aplicación registra automáticamente datos de tráfico simulados y los presenta en un gráfico.<br>3. El CLIENTE puede cambiar la aplicación asociada y solicitar detener o cancelar el Despliegue si esas acciones están habilitadas para su estado.<br>4. Tras el periodo cubierto por el pago inicial, la aplicación registra como máximo un cargo simulado por cada periodo mensual activo, con el desglose de recursos, SERVICIO_IA y SOPORTE contratado.<br>5. Si falla un cargo, comienza un plazo de gracia de 3 días; vencido sin regularización, el Despliegue se suspende.<br>6. Cuando se regulariza el importe pendiente, la aplicación reactiva automáticamente el Despliegue e inicia un nuevo periodo mensual. |
| **Resultado / postcondición** | El estado, los cambios y los cargos quedan registrados. El CLIENTE puede consultar el tráfico simulado; no se crean ni monitorizan recursos reales. |

## CU11 — Gestionar Catálogo

| Campo | Descripción |
|---|---|
| **Código** | CU11 |
| **Nombre** | Gestionar Catálogo |
| **Actor principal** | ADMINISTRADOR |
| **Descripción** | Permite mantener productos, atributos, tarifas y disponibilidad por proveedor y región. |
| **Precondiciones** | El ADMINISTRADOR ha iniciado sesión con permisos de gestión del Catálogo. |
| **Flujo principal** | 1. El ADMINISTRADOR solicita crear, modificar, consultar o retirar un Producto.<br>2. Introduce la información común y los atributos específicos del tipo: computación, almacenamiento, bases de datos, redes, seguridad, backup, monitorización, computación IA, servicio IA o soporte.<br>3. Define tarifas unitarias y la disponibilidad por proveedor y región.<br>4. La aplicación valida los datos y que el Producto tenga su detalle correspondiente.<br>5. La aplicación guarda el cambio y registra la acción en auditoría. |
| **Resultado / postcondición** | El Catálogo refleja la versión vigente del Producto. Presupuestos emitidos y periodos ya iniciados conservan sus precios anteriores. |

## CU12 — Consultar auditoría

| Campo | Descripción |
|---|---|
| **Código** | CU12 |
| **Nombre** | Consultar auditoría |
| **Actor principal** | AUDITOR |
| **Descripción** | Permite revisar de forma independiente las acciones sensibles registradas en KeyDeploy. |
| **Precondiciones** | El AUDITOR ha iniciado sesión con su rol. |
| **Flujo principal** | 1. El AUDITOR solicita consultar los registros.<br>2. Filtra por usuario, tipo de acción o intervalo de fechas.<br>3. La aplicación muestra el actor, la acción, la fecha, el elemento afectado y los valores anteriores y nuevos disponibles.<br>4. El AUDITOR revisa los resultados en modo de solo lectura. |
| **Resultado / postcondición** | Los registros se consultan sin alterarse. Ningún usuario puede modificarlos desde esta funcionalidad. |

## CU13 — Gestionar usuarios y roles

| Campo | Descripción |
|---|---|
| **Código** | CU13 |
| **Nombre** | Gestionar usuarios y roles |
| **Actor principal** | ADMINISTRADOR |
| **Descripción** | Permite gestionar cuentas internas de KeyDeploy y asignar o revocar roles autorizados. |
| **Precondiciones** | El ADMINISTRADOR ha iniciado sesión con permisos de gestión de usuarios. |
| **Flujo principal** | 1. El ADMINISTRADOR busca una cuenta interna o solicita crearla.<br>2. Introduce o actualiza los datos permitidos de la cuenta.<br>3. Asigna o revoca los roles internos que correspondan.<br>4. La aplicación valida los permisos y guarda los cambios.<br>5. La aplicación registra la operación en auditoría. |
| **Resultado / postcondición** | La cuenta queda actualizada con los permisos asignados y la operación queda registrada. El ADMINISTRADOR no obtiene por este caso de uso permiso para consultar la auditoría. |

## CU14 — Revisar propuestas de IA y decisiones

| Campo | Descripción |
|---|---|
| **Código** | CU14 |
| **Nombre** | Revisar propuestas de IA y decisiones |
| **Actor principal** | AUDITOR |
| **Descripción** | Permite comprobar qué datos mínimos se enviaron al Servicio de IA, qué respuesta devolvió y qué resultado obtuvo la validación de KeyDeploy. |
| **Precondiciones** | El AUDITOR ha iniciado sesión y existe una propuesta con trazabilidad registrada. |
| **Flujo principal** | 1. El AUDITOR selecciona una propuesta.<br>2. La aplicación muestra los requerimientos enviados y la respuesta estructurada recibida, sin exponer identificadores de cuenta innecesarios.<br>3. Muestra los resultados de las comprobaciones deterministas, los motivos de rechazo o aceptación y la decisión registrada.<br>4. El AUDITOR verifica que la IA no calculó precios ni sustituyó las reglas de la aplicación. |
| **Resultado / postcondición** | La trazabilidad queda consultada en modo de solo lectura; ni la propuesta ni el registro de auditoría se modifican. |

## CU15 — Registrar cuenta de CLIENTE

| Campo | Descripción |
|---|---|
| **Código** | CU15 |
| **Nombre** | Registrar cuenta de CLIENTE |
| **Actor principal** | Persona no registrada |
| **Descripción** | Permite a una persona crear una cuenta para utilizar las funciones de CLIENTE de KeyDeploy. |
| **Precondiciones** | La persona no tiene una cuenta y dispone de los datos requeridos para registrarse. |
| **Flujo principal** | 1. La persona solicita crear una cuenta.<br>2. Introduce los datos de alta y una contraseña.<br>3. La aplicación valida los campos y comprueba que el correo no esté registrado.<br>4. La aplicación crea la cuenta, protege la contraseña y asigna exclusivamente el rol CLIENTE.<br>5. La aplicación confirma el alta y permite iniciar sesión. |
| **Resultado / postcondición** | La cuenta queda creada con el rol CLIENTE. El formulario público no permite autoasignarse roles internos. |

## CU16 — Añadir o cancelar SOPORTE

| Campo | Descripción |
|---|---|
| **Código** | CU16 |
| **Nombre** | Añadir o cancelar SOPORTE |
| **Actor principal** | CLIENTE |
| **Descripción** | Permite cambiar la contratación simulada del servicio SOPORTE de un Despliegue activo propio, con efecto en el siguiente periodo mensual. |
| **Precondiciones** | El CLIENTE ha iniciado sesión y tiene un Despliegue propio activo. |
| **Flujo principal** | 1. El CLIENTE consulta los servicios SOPORTE disponibles y los que ya tiene contratados.<br>2. Solicita añadir un plan o cancelar el plan vigente.<br>3. La aplicación comprueba la propiedad y el estado activo del Despliegue.<br>4. La aplicación registra el cambio y su fecha prevista de aplicación.<br>5. La aplicación informa al CLIENTE de que el cambio entra en vigor al comienzo del siguiente periodo mensual. |
| **Resultado / postcondición** | El alta o la cancelación de SOPORTE queda programada para el siguiente periodo. El periodo mensual en curso conserva el servicio y el importe ya acordados. |
