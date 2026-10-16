# Decisiones de arquitectura - KeyDeploy

**Responsable de recopilación:** Alonso Jesús Rodríguez Betancor (arquitecto)  
**Estado:** borrador pendiente de revisión y ratificación por el equipo.  
**Base:** decisiones DEC-16 a DEC-37 registradas en `progreso_UT1.md` y acuerdos de diseño trabajados para los apartados 7, 9, 11 y 12.

## 1. Alcance y comportamiento

| ID / tema | Decisión | Consecuencia para la arquitectura |
|---|---|---|
| DEC-01/03 | La contratación, el pago y el despliegue se simulan. No se conectan medios de pago ni proveedores cloud reales en el alcance inicial. | La aplicación registra pedidos, pagos y recursos simulados; no necesita automatizar infraestructura real. |
| DEC-05/06 | La IA recomienda varias opciones; la lógica de la aplicación valida compatibilidad y requisitos, calcula precios y advierte si se supera el presupuesto. | Separar el servicio de recomendación de los validadores y del cálculo de precios. La IA no decide si una propuesta es válida. |
| DEC-18 | El tráfico posterior al despliegue simulado se registra automáticamente y se consulta mediante un gráfico; el cliente no debe predecir picos horarios. | Prever métricas vinculadas al despliegue y una consulta para su visualización. |
| DEC-28 | Para generar una recomendación son obligatorios la descripción funcional, región, presupuesto y número de usuarios. Si falta alguno, se solicita al cliente y no se generan propuestas ni valores predeterminados. | Validar que el proyecto esté completo antes de invocar el servicio de IA. |

## 2. Catálogo, regiones y precios

| ID / tema | Decisión | Consecuencia para el modelo |
|---|---|---|
| DEC-19/20 | El SERVICIO_IA es distinto de SOPORTE y su tarifa se cobra mensualmente mientras el despliegue esté activo; no se cobra por tokens ni por cada recomendación. | Distinguir el servicio de IA del soporte opcional y relacionarlos con sus cargos recurrentes. |
| DEC-31 | La región de la UE es obligatoria solo cuando el proyecto lo solicita expresamente. La aplicación valida la región seleccionada, no certifica por sí sola el cumplimiento legal general. | Modelar regiones y disponibilidad de productos por región; aplicar la restricción declarada en el proyecto. |
| DEC-32 | El precio de un producto parametrizable se calcula sumando `cantidad del atributo × tarifa unitaria` para cada atributo facturable. | El catálogo debe representar atributos configurables y sus tarifas; importes y límites siguen pendientes. |
| DEC-33 | Cada nuevo periodo mensual aplica las tarifas vigentes al inicio de ese periodo. Los presupuestos emitidos y los periodos ya iniciados conservan sus precios. | Guardar una instantánea de precios y desglose en cada presupuesto y periodo de facturación. |
| DEC-30/35/37 | No se calculan impuestos. El pago inicial cubre el primer periodo mensual y los conceptos de pago único; estos no se repiten en mensualidades posteriores. | Separar conceptos únicos de recurrentes y evitar duplicar el cobro del primer mes. |

## 3. Despliegues, cobros y soporte

| ID / tema | Decisión | Consecuencia para el modelo |
|---|---|---|
| DEC-21/22 | Cada periodo mensual activo genera un cargo simulado con desglose de recursos, SERVICIO_IA y SOPORTE si está contratado. | Registrar periodos y cargos recurrentes, con sus importes y conceptos. |
| DEC-23/24 | Si falla un cargo mensual, el despliegue permanece activo durante un plazo de gracia de tres días; después se suspende. | Representar el estado del cargo y del despliegue, además de la fecha límite del plazo de gracia. |
| DEC-25/26 | Al regularizar el importe pendiente, el despliegue se reactiva automáticamente y comienza un nuevo periodo mensual desde la fecha de reactivación. | Registrar la regularización, reactivación y fechas de los periodos, sin cobrar como activo el tiempo suspendido. |
| DEC-34 | El cliente puede añadir o cancelar SOPORTE mientras el despliegue esté activo; el cambio se aplica desde el siguiente periodo mensual. | Mantener el soporte separado del despliegue y conservar el estado aplicable a cada periodo. |

## 4. Usuarios, privacidad y control de acceso

| ID / tema | Decisión | Consecuencia para la arquitectura |
|---|---|---|
| DEC-29 | El cliente puede registrarse y el alta le asigna únicamente el rol CLIENTE. | El registro público no debe permitir asignar roles privilegiados. Los campos y validaciones concretos siguen pendientes. |
| DEC-17 | El ADMINISTRADOR no consulta ni modifica la auditoría. El AUDITOR puede consultarla en modo de solo lectura. | Separar las autorizaciones de administración y auditoría; restringir la consulta de auditoría. |
| DEC-36 | El servicio externo de IA recibe solo los datos mínimos necesarios para recomendar, sin identificadores de cuenta ni acceso directo al proyecto. | Preparar una carga reducida para la integración externa; no compartir el registro completo del proyecto. |

## 5. Organización propuesta en Laravel

Esta sección recoge una **propuesta de arquitectura para la documentación**, no afirma que todas las clases estén implementadas:

- Las rutas dirigen las peticiones a controladores.
- Los Requests validan los datos de entrada.
- Los controladores coordinan las operaciones y delegan la lógica de negocio.
- Los Services concentran reglas como recomendaciones, validación de arquitecturas, precios y facturación.
- Los Models representan las entidades y relaciones persistidas.
- Los Resources preparan las respuestas para la interfaz.
- Los Enums centralizan tipos y estados controlados.
- Las Policies y los componentes dedicados a IA se consideran ampliaciones futuras, de acuerdo con la plantilla.

La IA propone, pero la aplicación valida y calcula. Los pagos y despliegues permanecen simulados.

## 6. Aspectos pendientes

- Ratificar estas decisiones con el equipo y confirmar cuáles se mantienen en el alcance final.
- Definir tarifas unitarias, límites, subtipos de producto e importe mensual del SERVICIO_IA.
- Definir el catálogo y las condiciones de SOPORTE, además de confirmar esta ampliación con el profesor.
- Definir los campos y validaciones del registro de clientes.
- Precisar el origen y la confirmación de requisitos opcionales que pueda inferir la IA.
- Acordar las métricas simuladas y la estructura final de entidades para periodos, cargos y soporte.

## 7. Nota de uso

Este documento consolida decisiones para facilitar su revisión y traslado a los apartados de requisitos, dominio cloud, modelo conceptual y arquitectura Laravel. Las decisiones registradas en el progreso estaban pendientes de ratificación del equipo; no deben presentarse como acuerdos definitivos hasta que el grupo las confirme.
