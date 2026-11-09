# Decisiones de arquitectura - KeyDeploy

**Responsable de recopilación:** Alonso Jesús Rodríguez Betancor (arquitecto)  
**Estado:** borrador recopilado del progreso del proyecto; pendiente de revisión y ratificación por el equipo.  
**Base:** decisiones DEC-16 a DEC-37 registradas en `progreso_UT1.md` y acuerdos de diseño trabajados para los apartados 7, 9, 11 y 12.

## 1. Alcance y comportamiento

| ID / tema | Decisión | Consecuencia para la arquitectura |
|---|---|---|
| DEC-01/03 | La contratación, el pago y el despliegue se simulan. No se conectan medios de pago ni proveedores cloud reales en el alcance inicial. | La aplicación registra pedidos, pagos y recursos simulados; no necesita automatizar infraestructura real. |
| DEC-05/06 | La IA recomienda varias opciones; la lógica de la aplicación valida compatibilidad y requisitos, calcula precios y advierte si se supera el presupuesto. | Separar el servicio de recomendación de los validadores y del cálculo de precios. La IA no decide si una propuesta es válida. |
| DEC-18 | El tráfico posterior al despliegue simulado se registra automáticamente y se consulta mediante un gráfico; el cliente no debe predecir picos horarios. | Prever registros de métricas vinculados al despliegue y una consulta para su visualización. |
| DEC-28 | Para generar una recomendación son obligatorios la descripción funcional, región, presupuesto y número de usuarios. Si falta alguno, se solicita al cliente y no se generan propuestas ni valores predeterminados. | Validar que el proyecto esté completo antes de invocar el servicio de IA. |

## 2. Catálogo, regiones y precios

| ID / tema | Decisión | Consecuencia para el modelo |
|---|---|---|
| DEC-19/20 | El SERVICIO_IA es un producto separado de SOPORTE y su tarifa se cobra mensualmente mientras el despliegue esté activo; no se cobra por tokens ni por cada recomendación. | Distinguir el servicio de IA del soporte opcional y relacionar ambos con los cargos recurrentes correspondientes. |
| DEC-31 | La región de la UE es obligatoria solo cuando el proyecto lo solicita expresamente. La aplicación valida la región seleccionada, no certifica por sí sola el cumplimiento legal general. | Modelar las regiones y la disponibilidad de productos por región; aplicar la restricción según los requisitos declarados en el proyecto. |
| DEC-32 | El precio de un producto parametrizable se calcula sumando `cantidad del atributo × tarifa unitaria` para cada atributo facturable. | El catálogo debe poder representar atributos configurables y sus tarifas; los importes y límites aún están pendientes. |
| DEC-33 | Cada nuevo periodo mensual aplica las tarifas vigentes al inicio de ese periodo. Los presupuestos emitidos y los periodos ya iniciados conservan sus precios. | Guardar una instantánea del precio y desglose en cada presupuesto y periodo de facturación. |
| DEC-30/35/37 | No se calculan impuestos. El pago inicial cubre el primer periodo mensual y los conceptos de pago único; estos últimos no se repiten en mensualidades posteriores. | Separar conceptos únicos de recurrentes y evitar duplicar el cobro del primer mes. |

## 3. Despliegues, cobros y soporte

| ID / tema | Decisión | Consecuencia para el modelo |
|---|---|---|
| DEC-21/22 | Cada periodo mensual activo genera un cargo simulado con desglose de recursos, SERVICIO_IA y SOPORTE si está contratado. | Registrar periodos y cargos recurrentes, con importes y conceptos aplicados. |
| DEC-23/24 | Si falla un cargo mensual, el despliegue permanece activo durante un plazo de gracia de tres días; después se suspende. | Representar el estado del cargo y del despliegue, además de la fecha límite del plazo de gracia. |
| DEC-25/26 | Al regularizar el importe pendiente, el despliegue se reactiva automáticamente y comienza un nuevo periodo mensual desde la fecha de reactivación. | Registrar la regularización, reactivación y fechas de los periodos sin cobrar el tiempo suspendido como un periodo activo. |
| DEC-34 | El cliente puede añadir o cancelar SOPORTE mientras el despliegue esté activo; el cambio se aplica desde el siguiente periodo mensual. | Mantener el contrato de soporte separado del despliegue y conservar el estado aplicable a cada periodo. |

## 4. Usuarios, privacidad y control de acceso

| ID / tema | Decisión | Consecuencia para la arquitectura |
|---|---|---|
| DEC-29 | El cliente puede registrarse y el alta le asigna únicamente el rol CLIENTE. | El registro público no debe permitir asignar roles privilegiados. Los campos y validaciones concretos siguen pendientes. |
| DEC-17 | El ADMINISTRADOR no consulta ni modifica la auditoría. El AUDITOR puede consultarla en modo de solo lectura. | Separar las autorizaciones de administración y auditoría. Registrar las acciones relevantes y restringir la consulta de auditoría. |
| DEC-36 | El servicio externo de IA recibe solo los datos mínimos necesarios para recomendar, sin identificadores de cuenta ni acceso directo al proyecto. | Preparar una carga de datos reducida para la integración externa; no compartir el registro completo del proyecto. |

## 5. Organización propuesta en Laravel

Esta sección recoge la **propuesta de arquitectura elaborada para la documentación**, no una afirmación de que todas las clases ya estén implementadas:

- Las rutas dirigen las peticiones a controladores.
- Los Requests validan los datos de entrada.
- Los controladores coordinan las operaciones y delegan la lógica de negocio.
- Los Services concentran reglas como generación de recomendaciones, validación de arquitecturas, precios y facturación.
- Los Models representan las entidades y relaciones persistidas.
- Los Resources preparan las respuestas para la interfaz.
- Los Enums centralizan tipos y estados controlados.
- Las Policies y los componentes dedicados a IA se consideran ampliaciones futuras, de acuerdo con la plantilla.

La separación clave es que el servicio externo propone, pero la aplicación valida y calcula. Los pagos y despliegues permanecen simulados.

## 6. Aspectos pendientes

- Ratificación de estas decisiones por el equipo y confirmación de cuáles se mantienen en el alcance final.
- Definición de tarifas unitarias, límites, subtipos de producto e importe mensual del SERVICIO_IA.
- Definición del catálogo y las condiciones del SOPORTE, además de la confirmación del profesor sobre esta ampliación.
- Definición de los campos y validaciones del registro de clientes.
- Precisar el origen y la confirmación de requisitos opcionales que pueda inferir la IA.
- Acordar el detalle de las métricas simuladas y la estructura final de entidades para periodos, cargos y soporte.

## 7. Nota de uso

Este documento consolida decisiones para facilitar su revisión y traslado a los apartados de requisitos, dominio cloud, modelo conceptual y arquitectura Laravel. Las decisiones registradas en el progreso estaban pendientes de ratificación del equipo; por ello, no deben presentarse como acuerdos definitivos hasta que el grupo las confirme.
