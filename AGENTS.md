# Protocolo de especificación, pruebas e implementación

Estas reglas se aplican a cambios funcionales de KeyDeploy. Su propósito es que el requisito acordado sea el contrato común antes de diseñar pruebas o escribir código.

## 1. Antes de implementar: entender y especificar

1. Identifica la funcionalidad solicitada y revisa el contexto pertinente del repositorio: requisitos, casos de uso, reglas de negocio, arquitectura, modelos, migraciones, rutas, código y pruebas existentes. No asumas que una descripción anterior de la conversación sustituye a la documentación vigente.
2. Redacta una especificación de la funcionalidad **antes de delegar o modificar código**. Guárdala en `docs/specs/<nombre-corto>.md`, con un nombre descriptivo y estable. Esta especificación es el contrato de la tarea.
3. La especificación debe ser concreta y comprobable, e incluir, según aplique:
   - objetivo, alcance y exclusiones;
   - actores, permisos, propiedad de recursos y precondiciones;
   - flujo principal y resultados observables;
   - entradas, validaciones y errores;
   - reglas de negocio, estados y transiciones;
   - persistencia, efectos secundarios y límites transaccionales;
   - casos límite, seguridad y privacidad;
   - criterios de aceptación verificables;
   - decisiones tomadas, supuestos y cuestiones pendientes.
4. Mantén la concordancia con la documentación del proyecto. Si hay contradicciones, señálalas en la especificación y no elijas silenciosamente una interpretación que altere el comportamiento acordado.
5. No conviertas una ausencia de detalle en una regla inventada. Si una decisión pendiente afecta permisos, datos persistidos, dinero/precios, estados irreversibles, compatibilidad o alcance, pregunta al usuario antes de cerrar la especificación. Para detalles menores, propone un supuesto conservador, identifícalo explícitamente y pide confirmación cuando pueda cambiar la aceptación.
6. Presenta la especificación al usuario para revisión. No empieces la implementación hasta que el usuario la acepte o dé una instrucción clara para proceder con los supuestos identificados. Si el usuario modifica el alcance, actualiza primero la especificación y vuelve a compartir el contrato actualizado.

## 2. Delegación con separación entre pruebas e implementación

Una vez acordada la especificación, divide el trabajo entre dos subagentes independientes:

- **Agente de pruebas:** diseña y escribe las pruebas que expresan los criterios de aceptación y casos límite de la especificación. No debe diseñar pruebas para reproducir detalles internos de una implementación.
- **Agente de implementación:** implementa el comportamiento requerido por la especificación y las convenciones del proyecto. No debe recibir el diseño, nombres, archivos ni resultados de las pruebas preparados por el agente de pruebas.

La especificación aceptada es la única fuente de verdad funcional para ambos. Proporciona a cada agente el mismo texto de la especificación y el contexto base necesario del repositorio; no le proporciones al agente de implementación el plan de pruebas ni al agente de pruebas el plan, diff o salida del agente de implementación. Cada agente puede consultar el código preexistente necesario para seguir las convenciones, pero debe derivar su trabajo del contrato, no de las decisiones privadas del otro.

### Aislamiento obligatorio

- Ejecuta a los agentes en contextos y worktrees separados, ambos basados en el mismo estado inicial del repositorio. No permitas que uno lea el worktree, rama, mensajes, diff o archivos generados por el otro antes de que ambos terminen su trabajo independiente.
- Asegura que ambos tengan acceso a la especificación aceptada, incluso si esta todavía no forma parte del commit base; pásales su contenido como contrato, sin pasarles artefactos del otro agente.
- Si no es posible proporcionar aislamiento real con las herramientas disponibles, no simules que existe. Detén la delegación y solicita autorización para un procedimiento alternativo; no hagas que ambos trabajen sobre el mismo árbol de archivos.
- No pidas a un mismo agente que diseñe pruebas y luego implemente el código de esa misma tarea: eso rompe la independencia prevista.

## 3. Integración y verificación

1. Espera a que ambos agentes terminen antes de compartir sus resultados entre ellos o iniciar la integración.
2. Integra los cambios de forma controlada, conservando los cambios del usuario y resolviendo conflictos sin descartar trabajo ajeno. Revisa el diff completo.
3. Comprueba las pruebas contra la especificación, no solo contra el comportamiento que casualmente produzca el código. Ajusta una prueba únicamente si contradice el contrato; si el contrato resulta ambiguo, vuelve a especificar y pide decisión en vez de cambiarlo tácitamente.
4. Ejecuta las pruebas pertinentes y las comprobaciones de estilo/calidad requeridas por el proyecto. Informa con claridad de lo que pasó, falló o no pudo ejecutarse.
5. No amplíes el alcance durante la integración. Si aparece un requisito nuevo, trátalo como un cambio: actualiza y acuerda la especificación antes de implementarlo.

## 4. Límites y excepciones

- Estas reglas de doble delegación aplican a cambios funcionales con código y pruebas. No obligan a delegar consultas informativas, análisis sin cambios, ni tareas puramente editoriales o de mantenimiento de documentación.
- Una corrección urgente o un cambio que no pueda aislarse no autoriza a saltarse silenciosamente el protocolo: explica la limitación y pide al usuario que elija cómo proceder.
- Las reglas específicas existentes del proyecto y las instrucciones del usuario siguen vigentes. La especificación no puede autorizar acciones destructivas, cambios de dependencias, despliegues ni decisiones fuera del alcance solicitado.
