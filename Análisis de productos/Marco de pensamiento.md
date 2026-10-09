# Marco de pensamiento para diseñar el sistema

## Principio

Pensar en este orden:

**necesidad → actor y objetivo → flujo → decisiones y reglas → hechos que ocurren → datos → componentes técnicos**

No empezar por las tablas. Primero entender qué trabajo necesita hacer el sistema y qué compromisos debe cumplir; luego elegir cómo representarlo y almacenarlo.

Este marco combina análisis de dominio con una versión ligera de *event storming*: describir acciones, decisiones y hechos del negocio antes de definir clases o tablas.

## Pasos para analizar cada capacidad

### 1. Fijar el alcance

- ¿Qué problema del cliente resuelve esta capacidad?
- ¿Qué resultado observable debe obtener?
- ¿Qué queda explícitamente fuera de esta versión?

Escribir una frase de alcance y evitar mezclar capacidades distintas. Por ejemplo, **generar una recomendación de despliegue** y **desplegar infraestructura** son capacidades diferentes.

### 2. Identificar actor, objetivo e inicio

- ¿Quién inicia el proceso: cliente, administrador, sistema externo?
- ¿Qué quiere conseguir?
- ¿Qué evento o acción inicia el flujo?
- ¿Qué debe cumplirse antes de empezar?

Separar personas de sistemas externos. La IA y el proveedor de pagos participan en el flujo, pero no son usuarios del producto.

### 3. Describir el flujo sin hablar de pantallas ni tablas

Narrar el camino normal en pasos cortos. Después añadir:

- alternativas válidas;
- errores y reintentos;
- cancelaciones;
- condiciones que impiden continuar.

Preguntar en cada paso: **¿quién decide?, ¿qué información necesita?, ¿qué puede fallar?**

### 4. Marcar comandos, decisiones y hechos

- **Comando:** una intención dirigida al sistema, como `Solicitar propuesta` o `Cancelar suscripción`.
- **Decisión:** una regla que permite o impide continuar, como «¿hay créditos suficientes?».
- **Hecho/evento de dominio:** algo que ya ocurrió y puede ser importante después, como `Pago confirmado`, `Créditos reservados` o `Propuesta generada`.

Nombrar los eventos en pasado ayuda a no confundir una intención con un hecho. `Pedir pago` no significa `Pago confirmado`.

### 5. Escribir las reglas y las invariantes

Una regla debe poder comprobarse. Ejemplos para este proyecto:

- no se conceden créditos hasta confirmar el pago;
- una misma notificación de pago no puede abonar dos veces;
- una propuesta fallida no debe cobrar silenciosamente créditos;
- una recomendación de la IA no activa una suscripción;
- no se despliegan recursos sin aceptación explícita del cliente.

Si la regla depende de una decisión comercial aún no tomada —por ejemplo, la caducidad de créditos— anotarla como **pregunta abierta**, no inventar un valor.

### 6. Extraer conceptos y su ciclo de vida

Solo después de entender el flujo, preguntar:

- ¿Qué cosas tienen identidad propia y cambian con el tiempo? Posibles entidades: `PeticiónCliente`, `Propuesta`, `Pago`, `Suscripción`.
- ¿Qué valores describen algo y normalmente no tienen identidad propia? Posibles objetos de valor: detalle de precio, configuración de recursos, periodo de facturación.
- ¿Qué hechos históricos hay que conservar? Posibles eventos: pago confirmado, crédito consumido, cancelación solicitada.
- ¿Qué estados atraviesa cada concepto y qué transiciones son válidas?

No convertir automáticamente cada nombre del dominio en una tabla. Primero definir propósito, identidad, ciclo de vida y reglas.

### 7. Delimitar responsabilidades y límites externos

Para cada paso, indicar quién es responsable:

- aplicación propia;
- proveedor de IA;
- proveedor de pagos;
- equipo interno;
- plataforma de infraestructura, si realmente se aprovisionan recursos.

Registrar qué se envía al sistema externo, qué respuesta se espera, cómo se valida y qué pasa ante una interrupción. La aplicación debe conservar suficiente información para explicar y auditar sus propias decisiones.

### 8. Proponer el modelo y comprobarlo contra escenarios

Ahora sí, dibujar entidades, relaciones y datos necesarios. Para cada dato preguntarse:

- ¿quién lo crea y quién puede cambiarlo?
- ¿cuánto tiempo se conserva?
- ¿es un dato actual o un registro histórico?
- ¿qué regla depende de él?
- ¿se necesita para mostrar, cobrar, operar o auditar?

Comprobar el modelo narrando de nuevo el flujo, incluyendo fallos, duplicados y cancelaciones. Si hace falta una excepción especial en cada paso, revisar el modelo o aclarar la regla.

## Plantilla reutilizable

```text
Capacidad:
Resultado para el cliente:
Fuera de alcance:
Actor que inicia / objetivo:
Disparador y precondiciones:

Flujo normal:
1.
2.
3.

Alternativas y errores:
-

Comandos:
-
Decisiones/reglas:
-
Hechos/eventos que ocurren:
-

Conceptos y estados afectados:
-
Sistemas externos y datos intercambiados:
-
Preguntas abiertas:
-
Supuestos por validar:
-
```

## Mantener claras las certezas

Clasificar cada decisión en una de estas categorías:

- **Confirmado:** requisito acordado.
- **Propuesta:** opción técnica recomendada, todavía modificable.
- **Supuesto:** se usa provisionalmente para poder avanzar.
- **Pregunta abierta:** hace falta una decisión del negocio o del cliente.

Así se evita que una idea provisional —como vender un paquete de 200 créditos— termine convertida accidentalmente en una restricción del modelo.

## Orden de trabajo sugerido para este proyecto

Aplicar la plantilla, en este orden:

1. **Solicitar una propuesta:** requisitos, validación, créditos, llamada a IA, propuesta y fallo/reintento.
2. **Comprar y consumir créditos:** compra, confirmación del pago, saldo, reserva, consumo y liberación.
3. **Contratar mantenimiento:** cobertura tecnológica, vigencia, cobro y cancelación.
4. **Contratar despliegue:** primero decidir si solo se vende una recomendación o si también se aprovisiona infraestructura.
5. **Administrar el catálogo:** quién puede modificar productos, precios, tecnologías y recursos.

Para cada capacidad, cerrar primero las preguntas de negocio que alteran estados, cobros o permisos. Después refinar el modelo de datos y recién entonces proponer API, base de datos y arquitectura de componentes.
